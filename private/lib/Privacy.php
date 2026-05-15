<?php
declare(strict_types=1);
namespace Kuko;

final class Privacy
{
    public function __construct(private Db $db) {}

    public function anonymizeReservation(int $id): void
    {
        $this->db->execStmt(
            "UPDATE reservations SET name = 'anonymizovaný', phone = '', email = '', note = '', user_agent = '' WHERE id = ?",
            [$id]
        );
    }

    /** Anonymizes reservations whose created_at is older than $months. Returns count affected. */
    public function purgeOlderThan(int $months): int
    {
        $cutoff = (new \DateTimeImmutable("-{$months} months"))->format('Y-m-d H:i:s');
        $rows = $this->db->all("SELECT id FROM reservations WHERE created_at < ? AND name <> 'anonymizovaný'", [$cutoff]);
        foreach ($rows as $r) {
            $this->anonymizeReservation((int) $r['id']);
        }
        return count($rows);
    }

    /** @return array<int,array<string,mixed>> */
    public function exportByEmail(string $email): array
    {
        return $this->db->all(
            'SELECT * FROM reservations WHERE LOWER(email) = LOWER(?) ORDER BY created_at DESC',
            [trim($email)]
        );
    }
}
