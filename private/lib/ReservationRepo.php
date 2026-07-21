<?php
declare(strict_types=1);
namespace Kuko;

final class ReservationRepo
{
    public function __construct(private Db $db) {}

    public function create(array $d): int
    {
        $token = bin2hex(random_bytes(16));
        return $this->db->insert(
            'INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, note, ip_hash, view_token, recaptcha_score, user_agent)
             VALUES (:package, :wished_date, :wished_time, :kids_count, :name, :phone, :email, :note, :ip_hash, :view_token, :recaptcha_score, :user_agent)',
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
                ':view_token'      => $token,
                ':recaptcha_score' => $d['recaptcha_score'] ?? null,
                ':user_agent'      => $d['user_agent'] ?? null,
            ]
        );
    }

    public function findByToken(string $token): ?array
    {
        return $this->db->one('SELECT * FROM reservations WHERE view_token = ?', [$token]);
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM reservations WHERE id = ?', [$id]);
    }

    /**
     * Build the shared WHERE clause + params for list()/count().
     * @param array{status?:string,package?:string,from?:string,to?:string,q?:string} $filter
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(array $filter): array
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
        if (!empty($filter['q'])) {
            // Escape LIKE wildcards so a search for "50%" / "a_b" is treated
            // literally instead of as a pattern. The '!' escape char is used
            // (not the conventional backslash) because a literal '\' in the
            // ESCAPE clause is parsed inconsistently across engines — SQLite
            // takes "'\'" as a backslash, while default MySQL treats it as an
            // escaped quote and errors. '!' is neutral in both.
            $where[] = "(name LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
            $like = '%' . self::escapeLike((string) $filter['q']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        return [implode(' AND ', $where), $params];
    }

    /** Escape LIKE metacharacters (%, _) and the escape char itself with '!'. */
    private static function escapeLike(string $s): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
    }

    /** @param array{status?:string,package?:string,from?:string,to?:string,q?:string,limit?:int,offset?:int} $filter */
    public function list(array $filter = []): array
    {
        [$whereSql, $params] = $this->buildWhere($filter);
        $limit  = max(1, min(500, (int) ($filter['limit']  ?? 50)));
        $offset = max(0, (int) ($filter['offset'] ?? 0));
        return $this->db->all(
            'SELECT * FROM reservations WHERE ' . $whereSql
            . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    /** Total rows matching the same filters (for pagination). */
    public function count(array $filter = []): int
    {
        [$whereSql, $params] = $this->buildWhere($filter);
        $row = $this->db->one('SELECT COUNT(*) AS c FROM reservations WHERE ' . $whereSql, $params);
        return (int) ($row['c'] ?? 0);
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
