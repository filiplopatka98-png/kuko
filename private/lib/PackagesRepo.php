<?php
declare(strict_types=1);
namespace Kuko;

final class PackagesRepo
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $cache = null;

    public function __construct(private Db $db) {}

    public function find(string $code): ?array
    {
        $this->load();
        return $this->cache[$code] ?? null;
    }

    /** @return array<int,array<string,mixed>> ordered by sort_order */
    public function listActive(): array
    {
        return $this->db->all('SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, code');
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array
    {
        return $this->db->all('SELECT * FROM packages ORDER BY sort_order, code');
    }

    public function update(string $code, array $d): void
    {
        $this->db->execStmt(
            'UPDATE packages SET name = ?, duration_min = ?, blocks_full_day = ?, is_active = ?, sort_order = ? WHERE code = ?',
            [
                (string) $d['name'],
                (int) $d['duration_min'],
                (int) ($d['blocks_full_day'] ?? 0),
                (int) ($d['is_active'] ?? 1),
                (int) ($d['sort_order'] ?? 0),
                $code,
            ]
        );
        $this->cache = null;
    }

    private function load(): void
    {
        if ($this->cache !== null) return;
        $rows = $this->db->all('SELECT * FROM packages');
        $this->cache = [];
        foreach ($rows as $r) {
            $this->cache[(string) $r['code']] = $r;
        }
    }
}
