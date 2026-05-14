<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/private/lib/App.php';
\Kuko\App::bootstrap();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

use Kuko\Db;
use Kuko\Renderer;
use Kuko\Router;
use Kuko\ReservationRepo;
use Kuko\SettingsRepo;
use Kuko\PackagesRepo;
use Kuko\OpeningHoursRepo;
use Kuko\BlockedPeriodsRepo;
use Kuko\Availability;

try {
    $db = Db::fromConfig();
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Database connection failed</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    return;
}

$repo       = new ReservationRepo($db);
$settings   = new SettingsRepo($db);
$packages   = new PackagesRepo($db);
$hours      = new OpeningHoursRepo($db);
$blocked    = new BlockedPeriodsRepo($db);
$renderer   = new Renderer(APP_ROOT . '/private/templates/admin');
$router     = new Router();
$adminUser  = $_SERVER['PHP_AUTH_USER'] ?? ($_SERVER['REMOTE_USER'] ?? 'unknown');

$nowTz = new \DateTimeZone(\Kuko\Config::get('app.tz', 'Europe/Bratislava'));

$availability = fn() => new Availability($db, $settings, $packages, $hours, $blocked, new \DateTimeImmutable('now', $nowTz));

$flash = function (string $msg, string $type = 'ok') {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
};

$audit = function (string $action, string $table, int $id, array $payload = []) use ($db, $adminUser) {
    $secret = (string) \Kuko\Config::get('security.ip_hash_secret', '');
    $db->execStmt(
        'INSERT INTO admin_actions (admin_user, action, target_table, target_id, payload_json, ip_hash) VALUES (?,?,?,?,?,?)',
        [$adminUser, $action, $table, $id, json_encode($payload), hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $secret)]
    );
};

$flashes = $_SESSION['flash'] ?? [];
$_SESSION['flash'] = [];

// ===== Reservations =====
$router->get('/admin', function () use ($renderer, $repo, $adminUser, $flashes) {
    $filter = [
        'status'  => $_GET['status']  ?? null,
        'package' => $_GET['package'] ?? null,
        'from'    => $_GET['from']    ?? null,
        'to'      => $_GET['to']      ?? null,
    ];
    $rows = $repo->list(array_filter($filter, fn($v) => $v !== null && $v !== ''));
    echo $renderer->render('list', ['rows' => $rows, 'filter' => $filter, 'user' => $adminUser, 'flashes' => $flashes]);
});

$router->get('/admin/reservation/{id}', function (array $p) use ($renderer, $repo, $adminUser, $flashes) {
    $row = $repo->find((int) $p['id']);
    if ($row === null) {
        http_response_code(404);
        echo $renderer->render('not-found', ['user' => $adminUser, 'flashes' => $flashes]);
        return;
    }
    echo $renderer->render('detail', ['r' => $row, 'user' => $adminUser, 'flashes' => $flashes]);
});

$router->post('/admin/reservation/{id}/status', function (array $p) use ($repo, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $status = (string) ($_POST['status'] ?? '');
    try {
        $repo->setStatus((int) $p['id'], $status);
        if ($status === 'confirmed') $repo->markConfirmed((int) $p['id']);
        if ($status === 'cancelled') $repo->markCancelled((int) $p['id'], (string) ($_POST['reason'] ?? ''));
        $audit('set_status', 'reservations', (int) $p['id'], ['status' => $status]);
        $flash('Status zmenený na ' . $status . '.');
    } catch (\InvalidArgumentException) {
        http_response_code(400); echo 'bad status'; return;
    }
    header('Location: /admin/reservation/' . (int) $p['id']);
});

$router->post('/admin/reservation/{id}/move', function (array $p) use ($repo, $audit, $flash, $availability) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    $row = $repo->find($id);
    if ($row === null) { http_response_code(404); echo 'not found'; return; }
    $newDate = (string) ($_POST['wished_date'] ?? '');
    $newTime = (string) ($_POST['wished_time'] ?? '');
    // Compute availability excluding this reservation (temporary cancel)
    $repo->setStatus($id, 'cancelled');
    $slots = $availability()->forDate($newDate, (string) $row['package'])->slots;
    if (!in_array($newTime, $slots, true)) {
        // restore
        $repo->setStatus($id, (string) $row['status']);
        $flash('Termín nie je dostupný. Vyberte iný čas.', 'err');
        header('Location: /admin/reservation/' . $id);
        return;
    }
    $repo->moveTo($id, $newDate, $newTime);
    $repo->setStatus($id, (string) $row['status']); // restore original status
    $audit('move', 'reservations', $id, ['from' => $row['wished_date'] . ' ' . $row['wished_time'], 'to' => $newDate . ' ' . $newTime]);
    $flash("Termín presunutý na $newDate o $newTime.");
    header('Location: /admin/reservation/' . $id);
});

// ===== Settings =====
$router->get('/admin/settings', function () use ($renderer, $settings, $adminUser, $flashes) {
    echo $renderer->render('settings', ['settings' => $settings->all(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/settings', function () use ($settings, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    foreach (SettingsRepo::KNOWN_KEYS as $k) {
        if (isset($_POST[$k])) {
            $v = (string) (int) $_POST[$k];
            $settings->set($k, $v);
        }
    }
    $audit('update_settings', 'settings', 0, $_POST);
    $flash('Nastavenia uložené.');
    header('Location: /admin/settings');
});

// ===== Opening hours =====
$router->get('/admin/opening-hours', function () use ($renderer, $hours, $adminUser, $flashes) {
    echo $renderer->render('opening-hours', ['hours' => $hours->all(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/opening-hours', function () use ($hours, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    for ($d = 0; $d < 7; $d++) {
        $isOpen = !empty($_POST['is_open'][$d]);
        $from = (string) ($_POST['open_from'][$d] ?? '09:00');
        $to   = (string) ($_POST['open_to'][$d]   ?? '20:00');
        $hours->update($d, $isOpen, $from, $to);
    }
    $audit('update_opening_hours', 'opening_hours', 0);
    $flash('Otváracie hodiny uložené.');
    header('Location: /admin/opening-hours');
});

// ===== Blocked periods =====
$router->get('/admin/blocked-periods', function () use ($renderer, $blocked, $adminUser, $flashes) {
    echo $renderer->render('blocked-periods', ['rows' => $blocked->listAll(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/blocked-periods', function () use ($blocked, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $df = (string) ($_POST['date_from'] ?? '');
    $dt = (string) ($_POST['date_to']   ?? '');
    $tf = (string) ($_POST['time_from'] ?? '');
    $tt = (string) ($_POST['time_to']   ?? '');
    if ($df === '' || $dt === '') { $flash('Dátumy sú povinné.', 'err'); header('Location: /admin/blocked-periods'); return; }
    $id = $blocked->create(
        $df, $dt,
        $tf === '' ? null : $tf,
        $tt === '' ? null : $tt,
        (string) ($_POST['reason'] ?? '') ?: null
    );
    $audit('create_blocked', 'blocked_periods', $id);
    $flash('Blokované obdobie pridané.');
    header('Location: /admin/blocked-periods');
});
$router->post('/admin/blocked-periods/{id}/delete', function (array $p) use ($blocked, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    $blocked->delete($id);
    $audit('delete_blocked', 'blocked_periods', $id);
    $flash('Blokované obdobie zmazané.');
    header('Location: /admin/blocked-periods');
});

// ===== Packages =====
$router->get('/admin/packages', function () use ($renderer, $packages, $adminUser, $flashes) {
    echo $renderer->render('packages', ['rows' => $packages->listAll(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/packages/{code}', function (array $p) use ($packages, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $code = (string) $p['code'];
    $packages->update($code, [
        'name'            => (string) $_POST['name'],
        'duration_min'    => (int) $_POST['duration_min'],
        'blocks_full_day' => !empty($_POST['blocks_full_day']),
        'is_active'       => !empty($_POST['is_active']),
        'sort_order'      => (int) ($_POST['sort_order'] ?? 0),
    ]);
    $audit('update_package', 'packages', 0, ['code' => $code]);
    $flash("Balíček $code uložený.");
    header('Location: /admin/packages');
});

// ===== Calendar =====
$router->get('/admin/calendar', function () use ($renderer, $db, $blocked, $hours, $adminUser, $flashes, $nowTz) {
    $month = (string) ($_GET['month'] ?? (new \DateTimeImmutable('now', $nowTz))->format('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = (new \DateTimeImmutable('now', $nowTz))->format('Y-m'); }
    $start = new \DateTimeImmutable($month . '-01', $nowTz);
    $end   = $start->modify('last day of this month');
    $rows = $db->all(
        "SELECT id, package, wished_date, wished_time, status, name FROM reservations
         WHERE wished_date BETWEEN ? AND ? ORDER BY wished_date, wished_time",
        [$start->format('Y-m-d'), $end->format('Y-m-d')]
    );
    $blockedPeriods = $blocked->listOverlapping($start->format('Y-m-d'), $end->format('Y-m-d'));
    echo $renderer->render('calendar', [
        'month'     => $month,
        'start'     => $start,
        'end'       => $end,
        'prev'      => $start->modify('-1 month')->format('Y-m'),
        'next'      => $start->modify('+1 month')->format('Y-m'),
        'rows'      => $rows,
        'blocked'   => $blockedPeriods,
        'hours'     => $hours->all(),
        'user'      => $adminUser,
        'flashes'   => $flashes,
    ]);
});

$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$match = $router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
if ($match === null) {
    http_response_code(404);
    echo $renderer->render('not-found', ['user' => $adminUser, 'flashes' => $flashes]);
    return;
}
($match->handler)($match->params ?? []);
