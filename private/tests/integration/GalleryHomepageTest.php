<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\MediaRepo;
use PHPUnit\Framework\TestCase;

final class GalleryHomepageTest extends TestCase
{
    private Db $db;
    private string $dir;
    private MediaRepo $repo;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec("CREATE TABLE gallery_photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT NOT NULL, webp TEXT,
            alt_text TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0,
            is_visible INTEGER NOT NULL DEFAULT 1,
            on_homepage INTEGER NOT NULL DEFAULT 0,
            uploaded_at TEXT NOT NULL DEFAULT (datetime('now')))");
        $this->dir = sys_get_temp_dir() . '/kuko-homepg-' . uniqid();
        mkdir($this->dir, 0777, true);
        $this->repo = new MediaRepo($this->db, $this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
    }

    /** @return int[] inserted ids */
    private function insertVisible(int $n): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = $this->db->insert(
                'INSERT INTO gallery_photos (filename, webp, alt_text, sort_order, is_visible) VALUES (?,?,?,?,1)',
                ["p{$i}.jpg", null, "Alt {$i}", $i]
            );
        }
        return $ids;
    }

    private function homepageCount(): int
    {
        return (int) ($this->db->one('SELECT COUNT(*) AS c FROM gallery_photos WHERE on_homepage = 1')['c'] ?? 0);
    }

    public function testSetHomepageCapAt6(): void
    {
        $ids = $this->insertVisible(7);
        for ($i = 0; $i < 6; $i++) {
            $this->assertTrue($this->repo->setHomepage($ids[$i], true), "id {$ids[$i]} should turn on");
        }
        $this->assertFalse($this->repo->setHomepage($ids[6], true), '7th must be rejected by cap');
        $this->assertSame(6, $this->homepageCount());
        $row = $this->db->one('SELECT on_homepage FROM gallery_photos WHERE id = ?', [$ids[6]]);
        $this->assertSame(0, (int) $row['on_homepage']);
    }

    public function testSetHomepageOffAlwaysAllowed(): void
    {
        $ids = $this->insertVisible(6);
        foreach ($ids as $id) {
            $this->assertTrue($this->repo->setHomepage($id, true));
        }
        $this->assertSame(6, $this->homepageCount());
        $this->assertTrue($this->repo->setHomepage($ids[0], false));
        $this->assertSame(5, $this->homepageCount());
    }

    public function testSetHomepageReEnableSameIdWhenAlreadyOn(): void
    {
        $ids = $this->insertVisible(6);
        foreach ($ids as $id) {
            $this->assertTrue($this->repo->setHomepage($id, true));
        }
        // Re-setting an already-on id to on must succeed (not falsely hit the cap).
        $this->assertTrue($this->repo->setHomepage($ids[2], true));
        $this->assertSame(6, $this->homepageCount());
    }

    public function testHomepageSetReturnsPickedThenRandomFill(): void
    {
        $ids = $this->insertVisible(10);
        $marked = [$ids[0], $ids[4], $ids[8]];
        foreach ($marked as $id) {
            $this->assertTrue($this->repo->setHomepage($id, true));
        }
        $set = $this->repo->homepageSet();
        $this->assertCount(6, $set);
        $resultIds = array_map(fn($r) => (int) $r['id'], $set);
        $this->assertSame($resultIds, array_unique($resultIds), 'all distinct');
        foreach ($marked as $m) {
            $this->assertContains($m, $resultIds, "marked {$m} must be present");
        }
        // The 3 fill ids must come from the remaining visible set.
        $fill = array_values(array_diff($resultIds, $marked));
        $this->assertCount(3, $fill);
        foreach ($fill as $f) {
            $this->assertContains($f, $ids);
            $this->assertNotContains($f, $marked);
        }
    }

    public function testHomepageSetCappedAt6WhenMoreMarked(): void
    {
        $ids = $this->insertVisible(10);
        // Bypass the cap via direct SQL: mark 8.
        for ($i = 0; $i < 8; $i++) {
            $this->db->execStmt('UPDATE gallery_photos SET on_homepage = 1 WHERE id = ?', [$ids[$i]]);
        }
        $set = $this->repo->homepageSet();
        $this->assertCount(6, $set);
        $resultIds = array_map(fn($r) => (int) $r['id'], $set);
        // All 6 must be among the marked 8.
        foreach ($resultIds as $rid) {
            $this->assertContains($rid, array_slice($ids, 0, 8));
        }
    }

    public function testHomepageSetFewerThanSixTotal(): void
    {
        $ids = $this->insertVisible(4);
        $this->assertTrue($this->repo->setHomepage($ids[0], true));
        $set = $this->repo->homepageSet();
        $this->assertCount(4, $set);
    }

    public function testListVisibleUnchanged(): void
    {
        $ids = $this->insertVisible(5);
        $this->repo->setHomepage($ids[0], true);
        $this->repo->setVisibility($ids[4], false);
        $list = $this->repo->listVisible();
        $this->assertCount(4, $list, 'listVisible returns all visible regardless of on_homepage');
        $listIds = array_map(fn($r) => (int) $r['id'], $list);
        $this->assertContains($ids[0], $listIds);
        $this->assertNotContains($ids[4], $listIds);
    }
}
