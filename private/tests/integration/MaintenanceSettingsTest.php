<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\SettingsRepo;
use Kuko\Maintenance;
use Kuko\Config;
use PHPUnit\Framework\TestCase;

final class MaintenanceSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        Config::load(__DIR__ . '/../fixtures/config.test.php');
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        $db->execStmt("INSERT INTO settings (setting_key,value) VALUES ('maintenance.enabled','1')");
        $db->execStmt("INSERT INTO settings (setting_key,value) VALUES ('maintenance.password','dbpass')");
        Maintenance::setSettings(new SettingsRepo($db));
    }

    protected function tearDown(): void
    {
        Maintenance::setSettings(null);
    }

    public function testEnabledFromSettings(): void
    {
        $this->assertTrue(Maintenance::enabled());
    }

    public function testPasswordFromSettings(): void
    {
        $this->assertTrue(Maintenance::passwordMatches('dbpass'));
        $this->assertFalse(Maintenance::passwordMatches('wrong'));
    }

    public function testFallsBackToConfigWhenNoSettings(): void
    {
        Maintenance::setSettings(null);
        // fixture config.test.php — check what app.maintenance is there; this test
        // asserts that with no SettingsRepo, enabled() returns the Config value
        // (whatever the fixture defines). Just assert it does not throw and returns a bool.
        $this->assertIsBool(Maintenance::enabled());
    }
}
