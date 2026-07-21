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

    public function testSearchByNamePhoneEmail(): void
    {
        $this->repo->create($this->input(['name' => 'Janko Hraško', 'phone' => '+421915111', 'email' => 'janko@x.sk']));
        $this->repo->create($this->input(['name' => 'Anna Nová', 'phone' => '+421915222', 'email' => 'anna@y.sk']));
        $this->assertCount(1, $this->repo->list(['q' => 'Hraško']), 'match by name');
        $this->assertCount(1, $this->repo->list(['q' => '915222']), 'match by phone fragment');
        $this->assertCount(1, $this->repo->list(['q' => 'anna@y']), 'match by email fragment');
        $this->assertCount(0, $this->repo->list(['q' => 'nezhoda']));
    }

    public function testSearchEscapesLikeWildcards(): void
    {
        // A '%' in the query must match literally, not as a wildcard.
        $this->repo->create($this->input(['name' => '100% bavlna']));
        $this->repo->create($this->input(['name' => '100 percent']));
        $this->assertCount(1, $this->repo->list(['q' => '100%']), '% is literal, not wildcard');
        $this->assertSame(1, $this->repo->count(['q' => '100%']), 'count honours escaped %');
    }

    public function testSearchEscapesUnderscoreWildcard(): void
    {
        // '_' matches any single char in LIKE; escaped it must be literal.
        $this->repo->create($this->input(['name' => 'a_b']));
        $this->repo->create($this->input(['name' => 'axb']));
        $this->assertCount(1, $this->repo->list(['q' => 'a_b']), '_ is literal, not single-char wildcard');
    }

    public function testSearchEscapesBangEscapeChar(): void
    {
        // The '!' escape char itself must be escaped so a literal '!' still matches.
        $this->repo->create($this->input(['name' => 'wow!']));
        $this->repo->create($this->input(['name' => 'wow']));
        $this->assertCount(1, $this->repo->list(['q' => 'wow!']));
    }

    public function testCountHonoursFilters(): void
    {
        $this->repo->create($this->input(['package' => 'mini']));
        $this->repo->create($this->input(['package' => 'maxi']));
        $this->repo->create($this->input(['package' => 'maxi']));
        $this->assertSame(3, $this->repo->count());
        $this->assertSame(2, $this->repo->count(['package' => 'maxi']));
        $this->assertSame(0, $this->repo->count(['q' => 'ghost']));
    }

    public function testListRespectsLimitAndOffset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->repo->create($this->input(['name' => 'R' . $i]));
        }
        $this->assertCount(2, $this->repo->list(['limit' => 2, 'offset' => 0]));
        $this->assertCount(2, $this->repo->list(['limit' => 2, 'offset' => 2]));
        $this->assertCount(1, $this->repo->list(['limit' => 2, 'offset' => 4]));
    }
}
