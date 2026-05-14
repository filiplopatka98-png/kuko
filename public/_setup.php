<?php
// One-shot deployment helper — REMOVE AFTER USE.
// Use ?action=path|migrate|delete & token=<token-from-config>
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
        echo "actions: path | migrate | smoke | delete\n";
}
