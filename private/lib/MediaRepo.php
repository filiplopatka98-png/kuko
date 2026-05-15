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
            $webpName = $base . '.webp';
            @imagewebp($img, $this->dir . '/' . $webpName, 82);
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
