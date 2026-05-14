<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/private/lib/App.php';
\Kuko\App::bootstrap();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    return;
}

$date = (string) ($_GET['date'] ?? '');
$package = (string) ($_GET['package'] ?? '');

if ($date === '' || $package === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_params']);
    return;
}

try {
    $db = \Kuko\Db::fromConfig();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
    error_log('[api/availability] DB failed: ' . $e->getMessage());
    return;
}

$availability = new \Kuko\Availability(
    $db,
    new \Kuko\SettingsRepo($db),
    new \Kuko\PackagesRepo($db),
    new \Kuko\OpeningHoursRepo($db),
    new \Kuko\BlockedPeriodsRepo($db),
    new \DateTimeImmutable('now', new \DateTimeZone(\Kuko\Config::get('app.tz', 'Europe/Bratislava')))
);

$result = $availability->forDate($date, $package);

echo json_encode([
    'date'         => $date,
    'package'      => $package,
    'duration_min' => $result->durationMin,
    'slots'        => $result->slots,
    'reason'       => $result->reason,
]);
