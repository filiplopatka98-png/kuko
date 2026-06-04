<?php
// One-shot deployment helper — REMOVE AFTER USE.
// Use ?action=path|migrate|seed|smoke|delete & token=<token-from-config>
declare(strict_types=1);

require dirname(__DIR__) . '/private/lib/App.php';
\Kuko\App::bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$expectedToken = (string) \Kuko\Config::get('auth.secret', '');
$givenToken = (string) ($_GET['token'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $givenToken)) {
    http_response_code(403);
    echo "forbidden\n";
    return;
}

$action = (string) ($_GET['action'] ?? '');

switch ($action) {
    case 'path':
        echo "__DIR__:           " . __DIR__ . "\n";
        echo "dirname(__DIR__):  " . dirname(__DIR__) . "\n";
        echo "private/lib path:  " . realpath(__DIR__ . '/../private/lib') . "\n";
        echo "admin .htpasswd:   " . __DIR__ . "/admin/.htpasswd\n";
        echo "config.php:        " . realpath(__DIR__ . '/../config/config.php') . "\n";
        break;

    case 'migrate':
        try {
            $db = \Kuko\Db::fromConfig();
        } catch (\Throwable $e) {
            http_response_code(500);
            echo "DB connect failed: " . $e->getMessage() . "\n";
            return;
        }
        $db->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $applied = array_column($db->all('SELECT name FROM migrations'), 'name');
        $files = glob(dirname(__DIR__) . '/private/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                echo "= skip $name\n";
                continue;
            }
            echo "+ apply $name\n";
            $sql = (string) file_get_contents($file);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '') continue;
                $db->exec($stmt);
            }
            $db->execStmt('INSERT INTO migrations (name) VALUES (?)', [$name]);
            echo "  done\n";
        }
        echo "all migrations applied\n";
        break;

    case 'seed':
        try {
            require dirname(__DIR__) . '/private/scripts/seed-cms.php';
            echo "seed-cms done\n";
        } catch (\Throwable $e) {
            http_response_code(500);
            echo "seed failed: " . $e->getMessage() . "\n";
        }
        break;

    case 'fix-domain':
        // One-off cleanup after a domain rename: replace stale literals in
        // migrated content_blocks + settings (mail.*.subject/intro etc.).
        // Idempotent — second run reports 0 changes. Accepts query overrides:
        //   ?old=foo.sk&new=bar.sk  (default: kuko-detskysvet.sk → kukodetskysvet.sk)
        $old  = (string) ($_GET['old']  ?? 'kuko-detskysvet.sk');
        $new  = (string) ($_GET['new']  ?? 'kukodetskysvet.sk');
        $oldT = (string) ($_GET['oldT'] ?? 'KUKO-detskysvet.sk');
        $newT = (string) ($_GET['newT'] ?? 'KUKOdetskysvet.sk');
        try {
            $db = \Kuko\Db::fromConfig();
            $report = [];
            foreach ([
                ['content_blocks', 'block_key', 'value'],
                ['settings',       'setting_key', 'value'],
            ] as [$table, $keyCol, $valCol]) {
                foreach ([[$old, $new], [$oldT, $newT]] as [$from, $to]) {
                    if ($from === $to) continue;
                    $rows = $db->all("SELECT $keyCol AS k, $valCol AS v FROM $table WHERE $valCol LIKE ?", ['%' . $from . '%']);
                    foreach ($rows as $r) {
                        $newVal = str_replace($from, $to, (string) $r['v']);
                        if ($newVal === (string) $r['v']) continue;
                        $db->execStmt("UPDATE $table SET $valCol = ? WHERE $keyCol = ?", [$newVal, (string) $r['k']]);
                        $report[] = "+ {$table}.{$r['k']}: '{$from}' → '{$to}'";
                    }
                }
            }
            if ($report === []) echo "= nothing to fix\n";
            else echo implode("\n", $report) . "\n";
            echo "fix-domain done\n";
        } catch (\Throwable $e) {
            http_response_code(500);
            echo "fix-domain failed: " . $e->getMessage() . "\n";
        }
        break;

    case 'delete':
        if (@unlink(__FILE__)) {
            echo "deleted\n";
        } else {
            echo "delete failed\n";
        }
        break;

    case 'smoke':
        try {
            $db = \Kuko\Db::fromConfig();
            $row = $db->one('SELECT 1 AS ok');
            echo "DB SELECT 1 = " . ($row['ok'] ?? 'null') . "\n";
            echo "Tables:\n";
            foreach ($db->all('SHOW TABLES') as $r) {
                echo "  " . implode('|', array_values($r)) . "\n";
            }
        } catch (\Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        break;

    default:
        echo "actions: path | migrate | seed | smoke | fix-domain | delete\n";
}
