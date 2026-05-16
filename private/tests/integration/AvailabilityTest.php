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
        // MAXI 13:00-16:00 + symmetric buffer 30 = blocks [12:30, 16:30)
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('maxi', '2026-05-21', '13:00:00', 10, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'confirmed')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        // MINI is 120min. subtract removes [12:30, 16:30).
        // Before-buffer: last allowed MINI start is 10:30 (10:30+2h = 12:30 touches lower bound, OK).
        $this->assertContains('09:00', $r->slots);
        $this->assertContains('10:30', $r->slots);     // ends 12:30 boundary (before-buffer)
        $this->assertNotContains('11:00', $r->slots);  // ends 13:00 — now inside before-buffer
        $this->assertNotContains('11:30', $r->slots);  // would overlap (11:30+2h=13:30 inside block)
        $this->assertNotContains('12:00', $r->slots);
        $this->assertNotContains('15:00', $r->slots);
        $this->assertNotContains('16:00', $r->slots);
        $this->assertContains('17:00', $r->slots);
        $this->assertContains('16:30', $r->slots);  // first slot after buffer ends (16:30 + 2h = 18:30 ≤ 20:00) — after-buffer still validated
    }

    public function testExistingReservationBlocksBeforeWithBuffer(): void
    {
        // MAXI slot 14:00 for 180 min (14:00-17:00), buffer 30.
        // Symmetric buffer blocks [13:30, 17:30). A NEW MINI ending exactly at 14:00
        // (12:00-14:00) used to be ALLOWED by the old after-only buffer, but the
        // before-buffer now pushes the blocked region back to 13:30, so a MINI
        // starting at 12:00 (ends 14:00) overlaps 13:30-14:00 and must be rejected.
        $this->settings->set('buffer_min', '30');
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('maxi', '2026-05-21', '14:00:00', 10, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'confirmed')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        // 12:00 MINI ends 14:00 — under OLD logic this was offered; with the
        // before-buffer it overlaps [13:30, 14:00) and must NOT be offered.
        $this->assertNotContains('12:00', $r->slots);
        // 11:30 MINI ends 13:30 — touches the before-buffer lower bound, still OK.
        $this->assertContains('11:30', $r->slots);
        // After-buffer end: 17:00 + 30 = 17:30, first MINI after is 17:30.
        $this->assertContains('17:30', $r->slots);
        $this->assertNotContains('16:00', $r->slots);
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

    public function testClosedReservationBlocksOnlyItsWindow(): void
    {
        // A "closed" booking no longer blocks the whole day — it only blocks
        // its own time window (+buffer), like every other package. mini slots
        // outside [10:00-30, 14:00+30] stay available.
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('closed', '2026-05-21', '10:00:00', 20, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'pending')"
        );
        $r = $this->make()->forDate('2026-05-21', 'mini');
        $this->assertNotEmpty($r->slots);
        $this->assertNull($r->reason);
        $this->assertNotContains('10:00', $r->slots); // inside the closed window
        $this->assertNotContains('14:00', $r->slots); // still inside (ends 14:30)
        $this->assertContains('14:30', $r->slots);     // first free start after buffer
        $this->assertContains('18:00', $r->slots);     // last mini start (18:00+2h=20:00)
    }

    public function testRequestingClosedAllowedAtNonOverlappingTimes(): void
    {
        // Requesting "closed" when a mini partial booking exists is now
        // allowed at times that don't overlap the mini window (+buffer).
        $this->db->execStmt(
            "INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, ip_hash, status)
             VALUES ('mini', '2026-05-21', '10:00:00', 10, 'X', '+421900', 'x@x', '" . str_repeat('a', 64) . "', 'pending')"
        );
        $r = $this->make()->forDate('2026-05-21', 'closed');
        $this->assertNotEmpty($r->slots);
        $this->assertNull($r->reason);
        $this->assertNotContains('10:00', $r->slots); // overlaps the mini window
        $this->assertContains('12:30', $r->slots);     // first closed start after buffer
        $this->assertContains('16:00', $r->slots);     // last closed start (16:00+4h=20:00)
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
