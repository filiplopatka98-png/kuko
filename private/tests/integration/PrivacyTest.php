<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use Kuko\Db;
use Kuko\Privacy;
use PHPUnit\Framework\TestCase;

final class PrivacyTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec(
            "CREATE TABLE reservations (id INTEGER PRIMARY KEY AUTOINCREMENT, package TEXT, wished_date TEXT, wished_time TEXT,
             kids_count INTEGER, name TEXT, phone TEXT, email TEXT, note TEXT, status TEXT DEFAULT 'pending',
             ip_hash TEXT DEFAULT '', user_agent TEXT, created_at TEXT)"
        );
        $this->db->execStmt(
            "INSERT INTO reservations (package,wished_date,wished_time,kids_count,name,phone,email,note,status,user_agent,created_at)
             VALUES ('mini','2025-01-01','14:00',8,'Old Client','+421900111222','old@x.sk','poznamka','confirmed','UA1', ?)",
            [(new \DateTimeImmutable('-7 months'))->format('Y-m-d H:i:s')]
        );
        $this->db->execStmt(
            "INSERT INTO reservations (package,wished_date,wished_time,kids_count,name,phone,email,note,status,user_agent,created_at)
             VALUES ('maxi','2026-06-01','15:00',12,'Fresh Client','+421900333444','fresh@x.sk','','pending','UA2', ?)",
            [(new \DateTimeImmutable('-2 months'))->format('Y-m-d H:i:s')]
        );
    }

    public function testAnonymizeScrubsPiiKeepsStats(): void
    {
        $p = new Privacy($this->db);
        $p->anonymizeReservation(1);
        $r = $this->db->all('SELECT * FROM reservations WHERE id=1')[0];
        $this->assertSame('', (string) $r['email']);
        $this->assertSame('', (string) $r['phone']);
        $this->assertStringNotContainsStringIgnoringCase('Old Client', (string) $r['name']);
        $this->assertSame('', (string) $r['note']);
        $this->assertSame('', (string) $r['user_agent']);
        $this->assertSame('mini', (string) $r['package']);
        $this->assertSame(8, (int) $r['kids_count']);
        $this->assertSame('confirmed', (string) $r['status']);
    }

    public function testPurgeOlderThanAnonymizesOnlyOldRows(): void
    {
        $p = new Privacy($this->db);
        $this->assertSame(1, $p->purgeOlderThan(6));
        $this->assertSame('fresh@x.sk', (string) $this->db->all("SELECT email FROM reservations WHERE id=2")[0]['email']);
    }

    public function testPurgeIsIdempotent(): void
    {
        $p = new Privacy($this->db);
        $this->assertSame(1, $p->purgeOlderThan(6));
        $this->assertSame(0, $p->purgeOlderThan(6));
    }

    public function testExportByEmailCaseInsensitive(): void
    {
        $p = new Privacy($this->db);
        $rows = $p->exportByEmail('FRESH@X.SK');
        $this->assertCount(1, $rows);
        $this->assertSame('Fresh Client', (string) $rows[0]['name']);
    }
}
