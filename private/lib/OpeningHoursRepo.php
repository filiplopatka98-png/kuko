<?php
declare(strict_types=1);
namespace Kuko;

final class OpeningHoursRepo
{
    public const WEEKDAY_NAMES = [
        0 => 'Nedeľa',
        1 => 'Pondelok',
        2 => 'Utorok',
        3 => 'Streda',
        4 => 'Štvrtok',
        5 => 'Piatok',
        6 => 'Sobota',
    ];

    /** @var array<int,array<string,mixed>>|null indexed by weekday */
    private ?array $cache = null;

    public function __construct(private Db $db) {}

    /** Date::format('w') compatible: 0..6 Sunday..Saturday */
    public function forWeekday(int $weekday): ?array
    {
        $this->load();
        return $this->cache[$weekday] ?? null;
    }

    /** @return array<int,array<string,mixed>> indexed by weekday */
    public function all(): array
    {
        $this->load();
        return $this->cache ?? [];
    }

    public function update(int $weekday, bool $isOpen, string $openFrom, string $openTo): void
    {
        if ($weekday < 0 || $weekday > 6) {
            throw new \InvalidArgumentException("Invalid weekday: $weekday");
        }
        $this->db->execStmt(
            'UPDATE opening_hours SET is_open = ?, open_from = ?, open_to = ? WHERE weekday = ?',
            [$isOpen ? 1 : 0, $openFrom, $openTo, $weekday]
        );
        $this->cache = null;
    }

    private function load(): void
    {
        if ($this->cache !== null) return;
        $rows = $this->db->all('SELECT * FROM opening_hours');
        $this->cache = [];
        foreach ($rows as $r) {
            $this->cache[(int) $r['weekday']] = $r;
        }
    }
}
