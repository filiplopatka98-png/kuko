<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use Kuko\BlockedPeriodsRepo;
use Kuko\Db;
use Kuko\OpeningHoursRepo;
use Kuko\PackagesRepo;
use Kuko\SettingsRepo;
use PHPUnit\Framework\TestCase;

final class RepositoriesTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        $this->db->exec("CREATE TABLE packages (code TEXT PRIMARY KEY, name TEXT NOT NULL, duration_min INTEGER NOT NULL, blocks_full_day INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, description TEXT, price_text TEXT, kids_count_text TEXT, duration_text TEXT, included_json TEXT, accent_color TEXT)");
        $this->db->exec("CREATE TABLE opening_hours (weekday INTEGER PRIMARY KEY, is_open INTEGER NOT NULL DEFAULT 1, open_from TEXT NOT NULL, open_to TEXT NOT NULL)");
        $this->db->exec("CREATE TABLE blocked_periods (id INTEGER PRIMARY KEY AUTOINCREMENT, date_from TEXT NOT NULL, date_to TEXT NOT NULL, time_from TEXT, time_to TEXT, reason TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')))");
    }

    public function testSettingsSetGet(): void
    {
        $s = new SettingsRepo($this->db);
        $s->set('foo', 'bar');
        $this->assertSame('bar', $s->get('foo'));
        $this->assertSame('default', $s->get('missing', 'default'));
        $s->set('foo', 'baz');
        $this->assertSame('baz', $s->get('foo'));
        $this->assertSame(0, $s->getInt('missing_int'));
        $s->set('n', '42');
        $this->assertSame(42, $s->getInt('n'));
    }

    public function testPackagesListAndUpdate(): void
    {
        $this->db->execStmt("INSERT INTO packages (code, name, duration_min, sort_order) VALUES ('mini','MINI',120,1)");
        $this->db->execStmt("INSERT INTO packages (code, name, duration_min, sort_order, is_active) VALUES ('legacy','OLD',60,9,0)");
        $p = new PackagesRepo($this->db);
        $this->assertCount(1, $p->listActive());
        $this->assertCount(2, $p->listAll());
        $p->update('mini', ['name' => 'MINI v2', 'duration_min' => 150, 'blocks_full_day' => 0, 'is_active' => 1, 'sort_order' => 5]);
        $this->assertSame('MINI v2', $p->find('mini')['name']);
        $this->assertSame(150, (int) $p->find('mini')['duration_min']);
    }

    public function testUpdatePersistsExtendedFields(): void
    {
        $this->db->execStmt("INSERT INTO packages (code, name, duration_min, sort_order) VALUES ('mini','MINI',120,1)");
        $p = new PackagesRepo($this->db);
        $p->update('mini', [
            'name'            => 'MINI',
            'duration_min'    => 120,
            'blocks_full_day' => 0,
            'is_active'       => 1,
            'sort_order'      => 1,
            'description'     => '<p>Popis <strong>mini</strong></p>',
            'price_text'      => '120 €',
            'kids_count_text' => 'do 10',
            'duration_text'   => '2 h',
            'included_json'   => '["Polozka A","Polozka B"]',
            'accent_color'    => 'blue',
        ]);
        $row = $p->find('mini');
        $this->assertSame('<p>Popis <strong>mini</strong></p>', $row['description']);
        $this->assertSame('120 €', $row['price_text']);
        $this->assertSame('do 10', $row['kids_count_text']);
        $this->assertSame('2 h', $row['duration_text']);
        $this->assertSame('["Polozka A","Polozka B"]', $row['included_json']);
        $this->assertSame('blue', $row['accent_color']);
        $this->assertSame(['Polozka A', 'Polozka B'], json_decode((string) $row['included_json'], true));
    }

    public function testOpeningHoursUpdate(): void
    {
        for ($d = 0; $d < 7; $d++) {
            $this->db->execStmt("INSERT INTO opening_hours (weekday, is_open, open_from, open_to) VALUES (?,1,'09:00','20:00')", [$d]);
        }
        $h = new OpeningHoursRepo($this->db);
        $this->assertCount(7, $h->all());
        $h->update(0, false, '00:00', '00:00'); // Sunday closed
        $this->assertSame(0, (int) $h->forWeekday(0)['is_open']);
        $h->update(1, true, '10:00', '18:00');
        $this->assertStringStartsWith('10:00', (string) $h->forWeekday(1)['open_from']);
    }

    public function testOpeningHoursRejectsBadWeekday(): void
    {
        $h = new OpeningHoursRepo($this->db);
        $this->expectException(\InvalidArgumentException::class);
        $h->update(9, true, '09:00', '20:00');
    }

    public function testBlockedPeriodsCrud(): void
    {
        $b = new BlockedPeriodsRepo($this->db);
        $id1 = $b->create('2026-12-24', '2026-12-26', null, null, 'Vianoce');
        $id2 = $b->create('2026-06-01', '2026-06-01', '14:00', '16:00', 'Servis');
        $this->assertCount(2, $b->listAll());
        $this->assertCount(1, $b->listForDate('2026-12-25'));
        $this->assertCount(1, $b->listForDate('2026-06-01'));
        $this->assertCount(0, $b->listForDate('2026-07-01'));
        $this->assertCount(2, $b->listOverlapping('2026-01-01', '2026-12-31'));
        $this->assertTrue($b->delete($id1));
        $this->assertCount(1, $b->listAll());
    }
}
