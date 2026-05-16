<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/App.php';
\Kuko\App::bootstrap();

// Pending reservations the admin has not acted on within one month are
// abandoned: cancel them so their slot is freed. Availability already
// ignores >1-month-old pendings as a safety net; this makes the state
// explicit (admin list, audit) and is the authoritative cleanup.
try {
    $db = \Kuko\Db::fromConfig();
    $tz = new \DateTimeZone(\Kuko\Config::get('app.tz', 'Europe/Bratislava'));
    $cutoff = (new \DateTimeImmutable('now', $tz))->modify('-1 month')->format('Y-m-d H:i:s');
    $n = $db->execStmt(
        "UPDATE reservations
            SET status = 'cancelled',
                cancelled_at = CURRENT_TIMESTAMP,
                cancelled_reason = 'Automaticky zrušená — nepotvrdená do 1 mesiaca'
          WHERE status = 'pending' AND created_at < ?",
        [$cutoff]
    );
    fwrite(STDOUT, "[expire-pending] cancelled {$n} pending reservation(s) older than 1 month (cutoff {$cutoff})\n");
} catch (\Throwable $e) {
    fwrite(STDERR, '[expire-pending] ERROR ' . $e->getMessage() . "\n");
    exit(1);
}
