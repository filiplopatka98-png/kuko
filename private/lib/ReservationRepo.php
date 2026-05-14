<?php
declare(strict_types=1);
namespace Kuko;

final class ReservationRepo
{
    public function __construct(private Db $db) {}

    public function create(array $d): int
    {
        return $this->db->insert(
            'INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, note, ip_hash, recaptcha_score, user_agent)
             VALUES (:package, :wished_date, :wished_time, :kids_count, :name, :phone, :email, :note, :ip_hash, :recaptcha_score, :user_agent)',
            [
                ':package'         => $d['package'],
                ':wished_date'     => $d['wished_date'],
                ':wished_time'     => $d['wished_time'],
                ':kids_count'      => (int) $d['kids_count'],
                ':name'            => $d['name'],
                ':phone'           => $d['phone'],
                ':email'           => $d['email'],
                ':note'            => $d['note'] ?? null,
                ':ip_hash'         => $d['ip_hash'],
                ':recaptcha_score' => $d['recaptcha_score'] ?? null,
                ':user_agent'      => $d['user_agent'] ?? null,
            ]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM reservations WHERE id = ?', [$id]);
    }

    /** @param array{status?:string,package?:string,from?:string,to?:string,limit?:int,offset?:int} $filter */
    public function list(array $filter = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filter['status'])) {
            $where[] = 'status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['package'])) {
            $where[] = 'package = ?';
            $params[] = $filter['package'];
        }
        if (!empty($filter['from'])) {
            $where[] = 'wished_date >= ?';
            $params[] = $filter['from'];
        }
        if (!empty($filter['to'])) {
            $where[] = 'wished_date <= ?';
            $params[] = $filter['to'];
        }
        $limit  = max(1, min(500, (int) ($filter['limit']  ?? 50)));
        $offset = max(0, (int) ($filter['offset'] ?? 0));
        return $this->db->all(
            'SELECT * FROM reservations WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, Reservation::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        return $this->db->execStmt('UPDATE reservations SET status = ? WHERE id = ?', [$status, $id]) > 0;
    }

    public function markConfirmed(int $id): void
    {
        $this->db->execStmt('UPDATE reservations SET confirmed_at = CURRENT_TIMESTAMP WHERE id = ? AND confirmed_at IS NULL', [$id]);
    }

    public function markCancelled(int $id, string $reason = ''): void
    {
        $this->db->execStmt(
            'UPDATE reservations SET cancelled_at = CURRENT_TIMESTAMP, cancelled_reason = ? WHERE id = ?',
            [$reason !== '' ? $reason : null, $id]
        );
    }

    public function moveTo(int $id, string $newDate, string $newTime): bool
    {
        return $this->db->execStmt(
            'UPDATE reservations SET wished_date = ?, wished_time = ? WHERE id = ?',
            [$newDate, $newTime, $id]
        ) > 0;
    }
}
