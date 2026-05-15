<?php
// private/lib/MediaRepo.php
declare(strict_types=1);
namespace Kuko;

final class MediaRepo
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_DIM = 2000;
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private Db $db, private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file */
    public function upload(array $file, string $alt): array
    {
        if (($file['error'] ?? 1) !== 0) {
            throw new \RuntimeException('Upload zlyhal (error ' . $file['error'] . ').');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('Súbor je príliš veľký (max 5 MB).');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Nepovolený typ súboru. Povolené: JPG, PNG, WebP.');
        }
        $ext = self::ALLOWED[$mime];
        $base = 'gal_' . bin2hex(random_bytes(8));
        $filename = $base . '.' . $ext;
        $dest = $this->dir . '/' . $filename;

        $img = $this->loadImage($file['tmp_name'], $mime);
        $img = $this->resize($img, self::MAX_DIM);
        $this->saveImage($img, $dest, $mime);

        $webpName = null;
        if (function_exists('imagewebp')) {
            $candidate = $base . '.webp';
            if (@imagewebp($img, $this->dir . '/' . $candidate, 82)) {
                $webpName = $candidate;
            }
        }
        // GD images are freed by GC; imagedestroy() is a deprecated no-op on PHP 8.5.

        $sort = (int) ($this->db->one('SELECT COALESCE(MAX(sort_order),0)+1 AS s FROM gallery_photos')['s'] ?? 1);
        $id = $this->db->insert(
            'INSERT INTO gallery_photos (filename, webp, alt_text, sort_order) VALUES (?,?,?,?)',
            [$filename, $webpName, $alt, $sort]
        );
        return $this->db->one('SELECT * FROM gallery_photos WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listVisible(): array
    {
        return $this->db->all('SELECT * FROM gallery_photos WHERE is_visible = 1 ORDER BY sort_order, id');
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array
    {
        return $this->db->all('SELECT * FROM gallery_photos ORDER BY sort_order, id');
    }

    public function setVisibility(int $id, bool $visible): void
    {
        $this->db->execStmt('UPDATE gallery_photos SET is_visible = ? WHERE id = ?', [$visible ? 1 : 0, $id]);
    }

    private const HOMEPAGE_MAX = 6;

    /**
     * Toggle a photo's "show on homepage" flag.
     * Turning OFF is always allowed. Turning ON is rejected (returns false) when
     * the cap is already reached AND this id is not already on. Returns true on
     * a successful change (or a no-op re-enable of an already-on id).
     */
    public function setHomepage(int $id, bool $on): bool
    {
        if (!$on) {
            $this->db->execStmt('UPDATE gallery_photos SET on_homepage = 0 WHERE id = ?', [$id]);
            return true;
        }
        $alreadyOn = (int) ($this->db->one(
            'SELECT on_homepage FROM gallery_photos WHERE id = ?', [$id]
        )['on_homepage'] ?? 0) === 1;
        if (!$alreadyOn) {
            $count = (int) ($this->db->one(
                'SELECT COUNT(*) AS c FROM gallery_photos WHERE on_homepage = 1'
            )['c'] ?? 0);
            if ($count >= self::HOMEPAGE_MAX) {
                return false;
            }
        }
        $this->db->execStmt('UPDATE gallery_photos SET on_homepage = 1 WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Photos for the homepage gallery: up to 6 owner-picked (is_visible AND
     * on_homepage, ordered by sort_order,id, capped at 6); if fewer than 6,
     * fill the remaining slots with RANDOM other visible photos. Random fill is
     * done in PHP (shuffle) so it is DB-portable (no RAND()/RANDOM() SQL).
     * Result length = min(6, total visible). Rows match listVisible() shape.
     *
     * @return array<int,array<string,mixed>>
     */
    public function homepageSet(): array
    {
        $picked = $this->db->all(
            'SELECT * FROM gallery_photos WHERE is_visible = 1 AND on_homepage = 1 ORDER BY sort_order, id'
        );
        $picked = array_slice($picked, 0, self::HOMEPAGE_MAX);
        if (count($picked) >= self::HOMEPAGE_MAX) {
            return $picked;
        }
        $chosenIds = [];
        foreach ($picked as $row) {
            $chosenIds[(int) $row['id']] = true;
        }
        $candidates = $this->db->all(
            'SELECT * FROM gallery_photos WHERE is_visible = 1 ORDER BY sort_order, id'
        );
        $candidates = array_values(array_filter(
            $candidates,
            fn($row) => !isset($chosenIds[(int) $row['id']])
        ));
        shuffle($candidates);
        $need = self::HOMEPAGE_MAX - count($picked);
        return array_merge($picked, array_slice($candidates, 0, $need));
    }

    public function updateAlt(int $id, string $alt): void
    {
        $this->db->execStmt('UPDATE gallery_photos SET alt_text = ? WHERE id = ?', [$alt, $id]);
    }

    /** @param int[] $idOrder */
    public function reorder(array $idOrder): void
    {
        $pos = 1;
        foreach ($idOrder as $id) {
            $this->db->execStmt('UPDATE gallery_photos SET sort_order = ? WHERE id = ?', [$pos++, (int) $id]);
        }
    }

    public function delete(int $id): void
    {
        $row = $this->db->one('SELECT * FROM gallery_photos WHERE id = ?', [$id]);
        if ($row === null) return;
        @unlink($this->dir . '/' . $row['filename']);
        if (!empty($row['webp'])) {
            @unlink($this->dir . '/' . $row['webp']);
        }
        $this->db->execStmt('DELETE FROM gallery_photos WHERE id = ?', [$id]);
    }

    private function loadImage(string $path, string $mime): \GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => false,
        };
        if ($img === false) {
            throw new \RuntimeException('Obrázok sa nepodarilo načítať.');
        }
        return $img;
    }

    private function resize(\GdImage $img, int $max): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $max && $h <= $max) return $img;
        $ratio = $w / $h;
        if ($w > $h) { $nw = $max; $nh = (int) round($max / $ratio); }
        else         { $nh = $max; $nw = (int) round($max * $ratio); }
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private function saveImage(\GdImage $img, string $dest, string $mime): void
    {
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $dest, 85),
            'image/png'  => imagepng($img, $dest, 6),
            'image/webp' => imagewebp($img, $dest, 85),
            default      => false,
        };
        if (!$ok) {
            throw new \RuntimeException('Obrázok sa nepodarilo uložiť.');
        }
    }
}
