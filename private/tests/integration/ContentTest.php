<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\Content;
use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase
{
    protected function setUp(): void
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE content_blocks (
            block_key TEXT PRIMARY KEY, label TEXT NOT NULL,
            content_type TEXT NOT NULL DEFAULT 'text', value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now')), updated_by TEXT)");
        $db->execStmt("INSERT INTO content_blocks (block_key,label,content_type,value) VALUES ('hero.title','x','text','Z DB')");
        Content::setDb($db);
        Content::reset();
    }

    protected function tearDown(): void
    {
        Content::setDb(null);
        Content::reset();
    }

    public function testReturnsDbValue(): void
    {
        $this->assertSame('Z DB', Content::get('hero.title', 'fallback'));
    }

    public function testReturnsFallbackWhenMissing(): void
    {
        $this->assertSame('fallback', Content::get('nope.key', 'fallback'));
    }

    public function testYearTokenReplaced(): void
    {
        $this->assertSame('Copyright ' . date('Y'), Content::get('missing', 'Copyright {{year}}'));
    }

    public function testFallsBackGracefullyWithoutDb(): void
    {
        Content::setDb(null);
        Content::reset();
        $this->assertSame('safe', Content::get('any.key', 'safe'));
    }
}
