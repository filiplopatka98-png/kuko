<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/App.php';
\Kuko\App::bootstrap();

$months = (int) \Kuko\Config::get('privacy.retention_months', 6);
try {
    $db = \Kuko\Db::fromConfig();
    $n = (new \Kuko\Privacy($db))->purgeOlderThan($months);
    fwrite(STDOUT, '[retention] anonymized ' . $n . " reservation(s) older than {$months} months\n");
} catch (\Throwable $e) {
    fwrite(STDERR, '[retention] ERROR ' . $e->getMessage() . "\n");
    exit(1);
}
