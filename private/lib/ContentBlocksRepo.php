<?php
// private/lib/ContentBlocksRepo.php
declare(strict_types=1);
namespace Kuko;

final class ContentBlocksRepo
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $cache = null;

    public function __construct(private Db $db) {}

    public function get(string $key): ?string
    {
        $this->load();
        return isset($this->cache[$key]) ? (string) $this->cache[$key]['value'] : null;
    }

    public function find(string $key): ?array
    {
        $this->load();
        return $this->cache[$key] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $this->load();
        return array_values($this->cache);
    }

    /** @return array<string,array<int,array<string,mixed>>> grouped by key prefix before first dot */
    public function listGrouped(): array
    {
        $this->load();
        $groups = [];
        foreach ($this->cache as $key => $row) {
            $prefix = strpos($key, '.') !== false ? substr($key, 0, strpos($key, '.')) : $key;
            $groups[$prefix][] = $row;
        }
        return $groups;
    }

    public function set(string $key, string $value, string $contentType, string $updatedBy, string $label = ''): void
    {
        if ($contentType === 'html') {
            $value = HtmlSanitizer::clean($value);
        }
        $exists = $this->db->one('SELECT block_key FROM content_blocks WHERE block_key = ?', [$key]) !== null;
        if ($exists) {
            $this->db->execStmt(
                'UPDATE content_blocks SET value = ?, content_type = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE block_key = ?',
                [$value, $contentType, $updatedBy, $key]
            );
        } else {
            $this->db->execStmt(
                'INSERT INTO content_blocks (block_key, label, content_type, value, updated_by) VALUES (?,?,?,?,?)',
                [$key, $label !== '' ? $label : $key, $contentType, $value, $updatedBy]
            );
        }
        $this->cache = null;
    }

    private function load(): void
    {
        if ($this->cache !== null) return;
        $this->cache = [];
        foreach ($this->db->all('SELECT * FROM content_blocks ORDER BY block_key') as $row) {
            $this->cache[(string) $row['block_key']] = $row;
        }
    }
}
