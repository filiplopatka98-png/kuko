<?php
declare(strict_types=1);
namespace Kuko;

final class BlockedPeriodsRepo
{
    public function __construct(private Db $db) {}

    /** @return array<int,array<string,mixed>> */
    public function listForDate(string $date): array
    {
        return $this->db->all(
            'SELECT * FROM blocked_periods WHERE date_from <= ? AND date_to >= ? ORDER BY date_from',
            [$date, $date]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listOverlapping(string $dateFrom, string $dateTo): array
    {
        return $this->db->all(
            'SELECT * FROM blocked_periods WHERE date_from <= ? AND date_to >= ? ORDER BY date_from',
            [$dateTo, $dateFrom]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array
    {
        return $this->db->all('SELECT * FROM blocked_periods ORDER BY date_from DESC');
    }

    public function create(string $dateFrom, string $dateTo, ?string $timeFrom, ?string $timeTo, ?string $reason): int
    {
        return $this->db->insert(
            'INSERT INTO blocked_periods (date_from, date_to, time_from, time_to, reason) VALUES (?, ?, ?, ?, ?)',
            [$dateFrom, $dateTo, $timeFrom, $timeTo, $reason]
        );
    }

    public function delete(int $id): bool
    {
        return $this->db->execStmt('DELETE FROM blocked_periods WHERE id = ?', [$id]) > 0;
    }
}
