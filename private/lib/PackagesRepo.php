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
            'UPDATE packages SET name = ?, duration_min = ?, blocks_full_day = ?, is_active = ?, sort_order = ?, '
            . 'description = ?, price_text = ?, kids_count_text = ?, duration_text = ?, included_json = ?, accent_color = ? '
            . 'WHERE code = ?',
            [
                (string) $d['name'],
                (int) $d['duration_min'],
                (int) ($d['blocks_full_day'] ?? 0),
                (int) ($d['is_active'] ?? 1),
                (int) ($d['sort_order'] ?? 0),
                isset($d['description'])     ? (string) $d['description']     : null,
                isset($d['price_text'])      ? (string) $d['price_text']      : null,
                isset($d['kids_count_text']) ? (string) $d['kids_count_text'] : null,
                isset($d['duration_text'])   ? (string) $d['duration_text']   : null,
                isset($d['included_json'])   ? (string) $d['included_json']   : null,
                isset($d['accent_color'])    ? (string) $d['accent_color']    : null,
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
