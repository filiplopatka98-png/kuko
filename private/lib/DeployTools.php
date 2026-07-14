<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Deployment helpers (migrate / seed / smoke / fix-domain), formerly exposed by
 * the public token-gated public/_setup.php. They now live behind the admin
 * login and are invoked from the /admin/tools route — no public endpoint, no
 * secret in a URL. Each method returns a plaintext report to show in the admin.
 */
final class DeployTools
{
    /** Apply any not-yet-applied private/migrations/*.sql files. */
    public static function migrate(Db $db, string $migrationsDir): string
    {
        $out = [];
        $db->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $applied = array_column($db->all('SELECT name FROM migrations'), 'name');
        $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                $out[] = "= skip $name";
                continue;
            }
            $out[] = "+ apply $name";
            $sql = (string) file_get_contents($file);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '') continue;
                $db->exec($stmt);
            }
            $db->execStmt('INSERT INTO migrations (name) VALUES (?)', [$name]);
            $out[] = '  done';
        }
        $out[] = 'all migrations applied';
        return implode("\n", $out) . "\n";
    }

    /** Run the idempotent CMS seed, capturing its echoed progress. */
    public static function seed(string $seedScript): string
    {
        ob_start();
        try {
            require $seedScript;
        } catch (\Throwable $e) {
            ob_end_clean();
            return 'seed failed: ' . $e->getMessage() . "\n";
        }
        return (string) ob_get_clean();
    }

    /** Connectivity + schema smoke test. */
    public static function smoke(Db $db): string
    {
        $out = [];
        $row = $db->one('SELECT 1 AS ok');
        $out[] = 'DB SELECT 1 = ' . ($row['ok'] ?? 'null');
        $out[] = 'Tables:';
        foreach ($db->all('SHOW TABLES') as $r) {
            $out[] = '  ' . implode('|', array_values($r));
        }
        return implode("\n", $out) . "\n";
    }

    /**
     * Bulk literal replace in content_blocks + settings (post domain-rename
     * cleanup). Idempotent — a second run reports 0 changes.
     *
     * @param array<int,array{0:string,1:string}> $pairs [from, to] replacements
     */
    public static function fixDomain(Db $db, array $pairs): string
    {
        $report = [];
        foreach ([
            ['content_blocks', 'block_key', 'value'],
            ['settings',       'setting_key', 'value'],
        ] as [$table, $keyCol, $valCol]) {
            foreach ($pairs as [$from, $to]) {
                if ($from === '' || $from === $to) continue;
                $rows = $db->all("SELECT $keyCol AS k, $valCol AS v FROM $table WHERE $valCol LIKE ?", ['%' . $from . '%']);
                foreach ($rows as $r) {
                    $newVal = str_replace($from, $to, (string) $r['v']);
                    if ($newVal === (string) $r['v']) continue;
                    $db->execStmt("UPDATE $table SET $valCol = ? WHERE $keyCol = ?", [$newVal, (string) $r['k']]);
                    $report[] = "+ {$table}.{$r['k']}: '{$from}' → '{$to}'";
                }
            }
        }
        if ($report === []) return "= nothing to fix\nfix-domain done\n";
        return implode("\n", $report) . "\nfix-domain done\n";
    }
}
