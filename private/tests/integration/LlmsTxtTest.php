<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\Config;
use Kuko\Content;
use Kuko\LlmsTxt;
use PHPUnit\Framework\TestCase;

final class LlmsTxtTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        Config::load(__DIR__ . '/../fixtures/config.test.php');
        Content::setDb(null);
    }

    protected function tearDown(): void
    {
        Content::setDb(null);
    }

    public function testFallbackRenderWithoutDb(): void
    {
        // No DB → Content::get hits fallbacks; packageList falls back to defaults.
        $out = LlmsTxt::render(null);
        // Heading + tagline reference.
        $this->assertStringContainsString('# KUKO detský svet', $out);
        // Seed-identical contact fallbacks.
        $this->assertStringContainsString('Bratislavská 141, 921 01 Piešťany', $out);
        $this->assertStringContainsString('+421 915 319 934', $out);
        $this->assertStringContainsString('info@kukodetskysvet.sk', $out);
        $this->assertStringContainsString('Pondelok – Nedeľa: 9:00 – 20:00', $out);
        // Cennik rows.
        $this->assertStringContainsString('Dieťa do 1 roku: ZADARMO', $out);
        $this->assertStringContainsString('5,00 € / hod', $out);
        $this->assertStringContainsString('15,00 €', $out);
        // All 3 package defaults present.
        $this->assertStringContainsString('Oslava KUKO MINI', $out);
        $this->assertStringContainsString('Oslava KUKO MAXI', $out);
        $this->assertStringContainsString('Uzavretá spoločnosť', $out);
        $this->assertStringContainsString('120 – 150 € / balíček', $out);
        $this->assertStringContainsString('350 € / balíček', $out);
        // Reservation URLs include package code.
        $this->assertStringContainsString('/rezervacia?balicek=mini', $out);
        $this->assertStringContainsString('/rezervacia?balicek=closed', $out);
    }

    public function testLivePackagesAreUsedWhenDbReady(): void
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE packages (
            code TEXT PRIMARY KEY, name TEXT, duration_min INTEGER, blocks_full_day INTEGER,
            is_active INTEGER, sort_order INTEGER,
            description TEXT, price_text TEXT, kids_count_text TEXT, duration_text TEXT,
            included_json TEXT, accent_color TEXT
        )");
        // One row with a price that doesn't match the default — proves the live value wins.
        $db->execStmt(
            "INSERT INTO packages VALUES ('mini','Oslava KUKO MINI',120,0,1,1,'Test desc','999 € / balíček','do 8','2 h','[]','blue')"
        );
        $out = LlmsTxt::render($db);
        $this->assertStringContainsString('999 € / balíček', $out);
        $this->assertStringContainsString('Test desc', $out);
        $this->assertStringContainsString('do 8', $out);
    }

    public function testLiveContentBlocksAreUsed(): void
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE content_blocks (
            block_key TEXT PRIMARY KEY, label TEXT, content_type TEXT, value TEXT,
            updated_at TEXT, updated_by TEXT
        )");
        $db->execStmt(
            "INSERT INTO content_blocks (block_key,label,content_type,value) VALUES (?,?,?,?)",
            ['kontakt.phone', 'phone', 'text', '+421 999 000 000']
        );
        Content::setDb($db);
        $out = LlmsTxt::render(null);
        $this->assertStringContainsString('+421 999 000 000', $out);
        $this->assertStringNotContainsString('+421 915 319 934', $out, 'live DB phone must override seed fallback');
    }

    public function testRouterServesAndIsBypassedByMaintenance(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/index.php');
        $this->assertStringContainsString("/llms.txt", $idx);
        $this->assertStringContainsString('LlmsTxt::render', $idx);
        $this->assertStringContainsString('text/markdown', $idx);
        $maint = file_get_contents(\dirname(__DIR__, 3) . '/private/lib/Maintenance.php');
        $this->assertStringContainsString("'/llms.txt'", $maint, 'llms.txt must bypass the maintenance gate (matches robots/sitemap)');
        // robots.txt advertises llms.txt when indexing is on.
        $this->assertStringContainsString('LLM-Content', $idx);
    }
}
