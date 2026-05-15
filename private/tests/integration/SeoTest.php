<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\SettingsRepo;
use Kuko\Seo;
use PHPUnit\Framework\TestCase;

final class SeoTest extends TestCase
{
    private function repoWith(array $kv): SettingsRepo
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        foreach ($kv as $k => $v) {
            $db->execStmt('INSERT INTO settings (setting_key,value) VALUES (?,?)', [$k, $v]);
        }
        return new SettingsRepo($db);
    }

    protected function tearDown(): void { Seo::setSettings(null); }

    public function testFallbacksWhenNoSettings(): void
    {
        Seo::setSettings($this->repoWith([]));
        $r = Seo::resolve('home', 'FB Title', 'FB Desc', false, null);
        $this->assertSame('FB Title', $r['title']);
        $this->assertSame('FB Desc', $r['description']);
        $this->assertSame('noindex, nofollow', $r['robots']);
    }

    public function testDbOverridesWin(): void
    {
        Seo::setSettings($this->repoWith([
            'seo.home.title' => 'DB Title',
            'seo.home.description' => 'DB Desc',
            'seo.public_indexing' => '1',
        ]));
        $r = Seo::resolve('home', 'FB Title', 'FB Desc', false, null);
        $this->assertSame('DB Title', $r['title']);
        $this->assertSame('DB Desc', $r['description']);
        $this->assertSame('index, follow', $r['robots']);
    }

    public function testEmptyDbValueFallsBack(): void
    {
        Seo::setSettings($this->repoWith(['seo.home.title' => '']));
        $r = Seo::resolve('home', 'FB Title', 'FB Desc', false, null);
        $this->assertSame('FB Title', $r['title']);
    }

    public function testPageIndexingOverridePrecedence(): void
    {
        // global indexing true (via DB), but per-page $pageIndexing=false must win
        Seo::setSettings($this->repoWith(['seo.public_indexing' => '1']));
        $r = Seo::resolve('home', 'T', 'D', true, false);
        $this->assertSame('noindex, nofollow', $r['robots']);
        // and the inverse: global false, page true wins
        $r2 = Seo::resolve('home', 'T', 'D', false, true);
        $this->assertSame('index, follow', $r2['robots']);
    }

    public function testNullPageTypeUsesDefault(): void
    {
        Seo::setSettings($this->repoWith(['seo.default.title' => 'Default DB Title']));
        $r = Seo::resolve(null, 'FB', 'D', false, null);
        $this->assertSame('Default DB Title', $r['title']);
    }

    public function testGracefulWhenSettingsThrows(): void
    {
        // No settings injected and no usable config DB → must not throw, returns fallbacks
        Seo::setSettings(null);
        $r = Seo::resolve('home', 'Safe Title', 'Safe Desc', false, null);
        $this->assertSame('Safe Title', $r['title']);
        $this->assertSame('Safe Desc', $r['description']);
        $this->assertContains($r['robots'], ['noindex, nofollow', 'index, follow']);
    }
}
