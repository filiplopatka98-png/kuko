<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\ContentBlocksRepo;
use PHPUnit\Framework\TestCase;

final class ContentBlocksRepoTest extends TestCase
{
    private Db $db;
    private ContentBlocksRepo $repo;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec("CREATE TABLE content_blocks (
            block_key TEXT PRIMARY KEY, label TEXT NOT NULL,
            content_type TEXT NOT NULL DEFAULT 'text', value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now')), updated_by TEXT)");
        $this->repo = new ContentBlocksRepo($this->db);
    }

    public function testSetCreatesThenGet(): void
    {
        $this->repo->set('hero.title', 'Vitajte', 'text', 'tester');
        $this->assertSame('Vitajte', $this->repo->get('hero.title'));
    }

    public function testGetMissingReturnsNull(): void
    {
        $this->assertNull($this->repo->get('missing.key'));
    }

    public function testSetUpdatesExisting(): void
    {
        $this->repo->set('k', 'v1', 'text', 'a');
        $this->repo->set('k', 'v2', 'text', 'b');
        $this->assertSame('v2', $this->repo->get('k'));
        $rows = $this->repo->all();
        $this->assertCount(1, $rows);
    }

    public function testListGroupedByPrefix(): void
    {
        $this->repo->set('hero.title', 'a', 'text', 't');
        $this->repo->set('hero.subtitle', 'b', 'text', 't');
        $this->repo->set('footer.copyright', 'c', 'text', 't');
        $grouped = $this->repo->listGrouped();
        $this->assertArrayHasKey('hero', $grouped);
        $this->assertArrayHasKey('footer', $grouped);
        $this->assertCount(2, $grouped['hero']);
    }

    public function testCacheInvalidatesOnSet(): void
    {
        $this->repo->set('k', 'first', 'text', 't');
        $this->assertSame('first', $this->repo->get('k'));
        $this->repo->set('k', 'second', 'text', 't');
        $this->assertSame('second', $this->repo->get('k'));
    }

    public function testHtmlContentIsSanitizedOnSet(): void
    {
        $this->repo->set('about.lead', '<p>ok</p><script>alert(1)</script>', 'html', 't');
        $stored = $this->repo->get('about.lead');
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringContainsString('<p>ok</p>', $stored);
    }

    public function testUpdatedAtRefreshesOnUpdate(): void
    {
        $this->repo->set('k', 'v1', 'text', 'a');
        $first = $this->repo->find('k')['updated_at'];
        sleep(1);
        $this->repo->set('k', 'v2', 'text', 'b');
        $second = $this->repo->find('k')['updated_at'];
        $this->assertNotSame($first, $second, 'updated_at must refresh on UPDATE');
    }
}
