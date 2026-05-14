<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\ReservationRepo;
use PHPUnit\Framework\TestCase;

final class ReservationRepoTest extends TestCase
{
    private Db $db;
    private ReservationRepo $repo;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec("
            CREATE TABLE reservations (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              package TEXT NOT NULL,
              wished_date TEXT NOT NULL,
              wished_time TEXT NOT NULL,
              kids_count INTEGER NOT NULL,
              name TEXT NOT NULL,
              phone TEXT NOT NULL,
              email TEXT NOT NULL,
              note TEXT,
              status TEXT NOT NULL DEFAULT 'pending',
              ip_hash TEXT NOT NULL,
              view_token TEXT UNIQUE,
              recaptcha_score REAL,
              user_agent TEXT,
              created_at TEXT NOT NULL DEFAULT (datetime('now')),
              updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $this->repo = new ReservationRepo($this->db);
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'package' => 'mini', 'wished_date' => '2026-06-01', 'wished_time' => '14:00',
            'kids_count' => 10, 'name' => 'Test', 'phone' => '+421900000', 'email' => 't@t.sk',
            'note' => 'n', 'ip_hash' => str_repeat('a', 64), 'recaptcha_score' => 0.9, 'user_agent' => 'phpunit',
        ], $overrides);
    }

    public function testInsert(): void
    {
        $id = $this->repo->create($this->input());
        $this->assertGreaterThan(0, $id);
        $row = $this->repo->find($id);
        $this->assertSame('mini', $row['package']);
        $this->assertSame('pending', $row['status']);
    }

    public function testListByStatus(): void
    {
        $this->repo->create($this->input());
        $this->repo->create($this->input(['package' => 'maxi']));
        $rows = $this->repo->list(['status' => 'pending']);
        $this->assertCount(2, $rows);
    }

    public function testListByPackage(): void
    {
        $this->repo->create($this->input());
        $this->repo->create($this->input(['package' => 'maxi']));
        $rows = $this->repo->list(['package' => 'maxi']);
        $this->assertCount(1, $rows);
    }

    public function testListByDateRange(): void
    {
        $this->repo->create($this->input(['wished_date' => '2026-05-15']));
        $this->repo->create($this->input(['wished_date' => '2026-06-15']));
        $this->repo->create($this->input(['wished_date' => '2026-07-15']));
        $rows = $this->repo->list(['from' => '2026-06-01', 'to' => '2026-06-30']);
        $this->assertCount(1, $rows);
    }

    public function testChangeStatus(): void
    {
        $id = $this->repo->create($this->input());
        $this->assertTrue($this->repo->setStatus($id, 'confirmed'));
        $this->assertSame('confirmed', $this->repo->find($id)['status']);
    }

    public function testChangeStatusRejectsInvalid(): void
    {
        $id = $this->repo->create($this->input());
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->setStatus($id, 'bogus');
    }

    public function testFindMissingReturnsNull(): void
    {
        $this->assertNull($this->repo->find(9999));
    }
}
