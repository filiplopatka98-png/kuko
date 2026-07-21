<?php
declare(strict_types=1);
namespace Kuko;

final class Db
{
    /**
     * Per-request connection cache. A single PHP request touches the DB from
     * many places (Maintenance gate, page handler, Content/Seo/Social helpers,
     * repositories) — each formerly opened its own PDO. Caching the Db here
     * collapses those into one shared connection per request.
     */
    private static ?self $instance = null;

    public function __construct(private \PDO $pdo) {}

    public static function fromConfig(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $cfg = Config::get('db');
        // Dev convenience: if host begins with "sqlite:" treat the full host string as a DSN
        if (is_string($cfg['host']) && str_starts_with($cfg['host'], 'sqlite:')) {
            return self::$instance = self::fromDsn((string) $cfg['host']);
        }
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4');
        return self::$instance = new self(new \PDO($dsn, $cfg['user'], $cfg['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]));
    }

    /**
     * Drop the cached fromConfig() connection. Prod never needs this (one
     * request = one connection), but tests that exercise fromConfig() call it
     * in tearDown to stay isolated.
     */
    public static function resetConnection(): void
    {
        self::$instance = null;
    }

    public static function fromDsn(string $dsn): self
    {
        return new self(new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]));
    }

    public function exec(string $sql): int { return (int) $this->pdo->exec($sql); }

    public function execStmt(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->execStmt($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function pdo(): \PDO { return $this->pdo; }
}
