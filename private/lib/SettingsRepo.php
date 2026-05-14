<?php
declare(strict_types=1);
namespace Kuko;

final class SettingsRepo
{
    public const KNOWN_KEYS = ['buffer_min', 'horizon_days', 'lead_hours', 'slot_increment_min'];

    /** @var array<string,string>|null */
    private ?array $cache = null;

    public function __construct(private Db $db) {}

    public function get(string $key, ?string $default = null): ?string
    {
        $this->load();
        return $this->cache[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $v = $this->get($key);
        return $v === null ? $default : (int) $v;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $this->load();
        return $this->cache ?? [];
    }

    public function set(string $key, string $value): void
    {
        $affected = $this->db->execStmt('UPDATE settings SET value = ? WHERE setting_key = ?', [$value, $key]);
        if ($affected === 0) {
            try {
                $this->db->execStmt('INSERT INTO settings (setting_key, value) VALUES (?, ?)', [$key, $value]);
            } catch (\PDOException) {
                $this->db->execStmt('UPDATE settings SET value = ? WHERE setting_key = ?', [$value, $key]);
            }
        }
        $this->cache = null;
    }

    private function load(): void
    {
        if ($this->cache !== null) return;
        $rows = $this->db->all('SELECT setting_key, value FROM settings');
        $this->cache = [];
        foreach ($rows as $r) {
            $this->cache[(string) $r['setting_key']] = (string) $r['value'];
        }
    }
}
