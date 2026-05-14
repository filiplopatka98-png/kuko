<?php
declare(strict_types=1);

require __DIR__ . '/../lib/autoload.php';
\Kuko\Config::load(__DIR__ . '/../../config/config.php');

$db = \Kuko\Db::fromConfig();
$db->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');

$applied = array_column($db->all('SELECT name FROM migrations'), 'name');
$files = glob(__DIR__ . '/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "= skip $name\n";
        continue;
    }
    echo "+ apply $name\n";
    $sql = (string) file_get_contents($file);
    // Strip line-level SQL comments so they don't mask following statements
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '') continue;
        $db->exec($stmt);
    }
    $db->execStmt('INSERT INTO migrations (name) VALUES (?)', [$name]);
    echo "  done\n";
}

echo "all migrations applied\n";
