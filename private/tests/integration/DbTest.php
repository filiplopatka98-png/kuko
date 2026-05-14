<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use PHPUnit\Framework\TestCase;

final class DbTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
    }

    public function testInsertAndFetch(): void
    {
        $id = $this->db->insert('INSERT INTO t (name) VALUES (?)', ['alice']);
        $this->assertGreaterThan(0, $id);
        $row = $this->db->one('SELECT * FROM t WHERE id = ?', [$id]);
        $this->assertSame('alice', $row['name']);
    }

    public function testFetchAll(): void
    {
        $this->db->insert('INSERT INTO t (name) VALUES (?)', ['a']);
        $this->db->insert('INSERT INTO t (name) VALUES (?)', ['b']);
        $rows = $this->db->all('SELECT name FROM t ORDER BY id');
        $this->assertSame(['a', 'b'], array_column($rows, 'name'));
    }

    public function testUpdate(): void
    {
        $id = $this->db->insert('INSERT INTO t (name) VALUES (?)', ['x']);
        $affected = $this->db->execStmt('UPDATE t SET name = ? WHERE id = ?', ['y', $id]);
        $this->assertSame(1, $affected);
    }

    public function testFetchOneMissingReturnsNull(): void
    {
        $this->assertNull($this->db->one('SELECT * FROM t WHERE id = ?', [9999]));
    }
}
