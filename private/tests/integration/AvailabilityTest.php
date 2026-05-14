<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use Kuko\Availability;
use Kuko\BlockedPeriodsRepo;
use Kuko\Db;
use Kuko\OpeningHoursRepo;
use Kuko\PackagesRepo;
use Kuko\SettingsRepo;
use PHPUnit\Framework\TestCase;

final class AvailabilityTest extends TestCase
{
    private Db $db;
    private SettingsRepo $settings;
    private PackagesRepo $packages;
    private OpeningHoursRepo $hours;
    private BlockedPeriodsRepo $blocked;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        // Schema (SQLite-flavoured)
        $this->db->exec("
            CREATE TABLE packages (
              code TEXT PRIMARY KEY, name TEXT NOT NULL, duration_min INTEGER NOT NULL,
              blocks_full_day INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1,
              sort_order INTEGER NOT NULL DEFAULT 0
            )
        ");
        $this->db->exec("
            CREATE TABLE opening_hours (
              weekday INTEGER PRIMARY KEY, is_open INTEGER NOT NULL DEFAULT 1,
              open_from TEXT NOT NULL DEFAULT '09:00:00', open_to TEXT NOT NULL DEFAULT '20:00:00'
            )
        ");
        $this->db->exec("
            CREATE TABLE blocked_periods (
              id INTEGER PRIMARY KEY AUTOINCREMENT, date_from TEXT NOT NULL, date_to TEXT NOT NULL,
              time_from TEXT, time_to TEXT, reason TEXT,
              created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $this->db->exec("
            CREATE TABLE settings (
              setting_key TEXT PRIMARY KEY, value TEXT NOT NULL,
              updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $this->db->exec("
            CREATE TABLE reservations (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              package TEXT NOT NULL, wished_date TEXT NOT NULL, wished_time TEXT NOT NULL,
              kids_count INTEGER NOT NULL, name TEXT NOT NULL, phone TEXT NOT NULL, email TEXT NOT NULL,
              note TEXT, status TEXT NOT NULL DEFAULT 'pending',
              ip_hash TEXT NOT NULL, recaptcha_score REAL, user_agent TEXT,
              created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        // Seed
        $this->db->execStmt("INSERT INTO packages (code, name, duration_min, blocks_full_day, sort_order) VALUES ('mini','MINI',120,0,1)");
        $this->db->execStmt("INSERT INTO packages (code, name, duration_min, blocks_full_day, sort_order) VALUES ('maxi','MAXI',180,0,2)");
        $this->db->execStmt("INSERT INTO packages (code, name, duration_min, blocks_full_day, sort_order) VALUES ('closed','CLOSED',240,1,3)");
        for ($d = 0; $d < 7; $d++) {
            $this->db->execStmt("INSERT INTO opening_hours (weekday, is_open, open_from, open_to) VALUES (?, 1, '09:00:00', '20:00:00')", [$d]);
        }
        foreach ([['buffer_min','30'],['horizon_days','180'],['lead_hours','24'],['slot_increment_min','30']] as $kv) {
            $this->db->execStmt('INSERT INTO settings (setting_key, value) VALUES (?, ?)', $kv);
        }

        $this->settings = new SettingsRepo($this->db);
        $this->packages = new PackagesRepo($this->db);
        $this->hours    = new OpeningHoursRepo($this->db);
        $this->blocked  = new BlockedPeriodsRepo($this->db);
    }

    private function make(string $nowIso = '2026-05-14 12:00:00'): Availability
    {
        return new Availability(
            $this->db, $this->settings, $this->packages, $this->hours, $this->blocked,
            new \DateTimeImmutable($nowIso, new \DateTimeZone('Europe/Bratislava'))
        );
    }

    public function testOpenDayMiniProducesAllSlots(): void
    {
        $r = $this->make()->forDate('2026-05-21', 'mini'); // Thursday far in future
        // duration 120 + slot step 30, open 9..20 → start times 09:00..18:00 (last fits 18:00-20:00)
        $expectedCount = ((20 - 9) * 60 / 30) - (120 / 30) + 1; // = 22-4+1=19
        $this->assertCount($expectedCount, $r->slots);
        $this->assertSame('09:00', $r->slots[0]);
        $this->assertSame('18:00', $r->slots[count($r->slots) - 1]);
        $this->assertNull($r->reason);
    }

    public function testClosedDayReturnsEmpty(): void
    {
        $this->db->execStmt('UPDATE opening_hours SET is_open = 0 WHERE weekday = 4'); // Thursday
        $r = $this->make()->forDate('2026-05-21', 'mini');
        $this->assertSame([], $r->slots);
        $this->assertSame('closed_day', $r->reason);
    }

    public function testBeforeLeadReturnsEmpty(): void
    {
        $r = $this->make('2026-05-21 12:00:00')->forDate('2026-05-21', 'mini');
        $this->assertSame('before_lead', $r->reason);
    }

    public function testAfterHorizonReturnsEmpty(): void
    {
        $this->settings->set('horizon_days', '30');
        $r = $this->make('2026-05-14 12:00:00')->forDate('2026-09-30', 'mini');
        $this->assertSame('after_horizon', $r->reason);
    }

    public function testExistingReservationBlocksWithBuffer(): void
    {
        // MAXI 13:00-16:00 + buffer 30 = blocks until 16:30
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('maxi', '2026-05-21', '13:00:00', 10, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'confirmed')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        // MINI is 120min. Allowed: 09:00..11:00, after 16:30 → 17:00, 18:00.
        // Between 11:00 and 13:00 also OK (start at 11:00 ends 13:00 = conflicting? No — overlap check excludes boundary? Let me re-check.)
        // Actually subtract removes [13:00, 16:30). Start 11:00 finishes 13:00 — touches but does not overlap. OK.
        $this->assertContains('09:00', $r->slots);
        $this->assertContains('11:00', $r->slots);  // ends 13:00 boundary
        $this->assertNotContains('11:30', $r->slots);  // would overlap (11:30+2h=13:30 inside block)
        $this->assertNotContains('12:00', $r->slots);
        $this->assertNotContains('15:00', $r->slots);
        $this->assertNotContains('16:00', $r->slots);
        $this->assertContains('17:00', $r->slots);
        $this->assertContains('16:30', $r->slots);  // first slot after buffer ends (16:30 + 2h = 18:30 ≤ 20:00)
    }

    public function testCancelledReservationDoesNotBlock(): void
    {
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('maxi', '2026-05-21', '13:00:00', 10, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'cancelled')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        $this->assertContains('13:00', $r->slots);
    }

    public function testClosedReservationBlocksWholeDay(): void
    {
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('closed', '2026-05-21', '10:00:00', 20, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'pending')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        $this->assertSame([], $r->slots);
        $this->assertSame('blocked_full_day', $r->reason);
    }

    public function testRequestingClosedWhenPartialBooking(): void
    {
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('mini', '2026-05-21', '10:00:00', 10, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'pending')"
        );
        $r = $this->make()->forDate('2026-05-21', 'closed');
        $this->assertSame([], $r->slots);
        $this->assertSame('blocked_full_day', $r->reason);
    }

    public function testBlockedPeriodAllDay(): void
    {
        $this->db->execStmt(
            "INSERT INTO blocked_periods (date_from, date_to, time_from, time_to, reason)
             VALUES ('2026-05-21', '2026-05-21', NULL, NULL, 'Sviatok')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        $this->assertSame('blocked_full_day', $r->reason);
    }

    public function testBlockedPeriodTimeWindow(): void
    {
        // Block 12:00-15:00
        $this->db->execStmt(
            "INSERT INTO blocked_periods (date_from, date_to, time_from, time_to, reason)
             VALUES ('2026-05-21', '2026-05-21', '12:00:00', '15:00:00', 'Servis')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        $this->assertContains('09:00', $r->slots);
        $this->assertContains('10:00', $r->slots);    // 10:00-12:00 OK
        $this->assertNotContains('10:30', $r->slots); // overlaps block
        $this->assertNotContains('11:00', $r->slots);
        $this->assertContains('15:00', $r->slots);    // 15:00-17:00 OK
    }

    public function testTodayShiftsByLeadHours(): void
    {
        $this->settings->set('lead_hours', '2');
        // Now 12:00, lead 2h → earliest start 14:00 today
        $r = $this->make('2026-05-14 12:00:00')->forDate('2026-05-14', 'mini');
        $this->assertNotContains('09:00', $r->slots);
        $this->assertNotContains('13:30', $r->slots);
        $this->assertContains('14:00', $r->slots);
    }

    public function testUnknownPackage(): void
    {
        $r = $this->make()->forDate('2026-05-21', 'bogus');
        $this->assertSame('unknown_package', $r->reason);
    }

    public function testBadDateFormat(): void
    {
        $r = $this->make()->forDate('21.05.2026', 'mini');
        $this->assertSame('bad_date', $r->reason);
    }
}
