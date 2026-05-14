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

try {
    $db = Db::fromConfig();
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Database connection failed</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    return;
}

$repo     = new ReservationRepo($db);
$renderer = new Renderer(APP_ROOT . '/private/templates/admin');
$router   = new Router();
$adminUser = $_SERVER['PHP_AUTH_USER'] ?? ($_SERVER['REMOTE_USER'] ?? 'unknown');

$router->get('/admin', function () use ($renderer, $repo, $adminUser) {
    $filter = [
        'status'  => $_GET['status']  ?? null,
        'package' => $_GET['package'] ?? null,
        'from'    => $_GET['from']    ?? null,
        'to'      => $_GET['to']      ?? null,
    ];
    $rows = $repo->list(array_filter($filter, fn($v) => $v !== null && $v !== ''));
    echo $renderer->render('list', ['rows' => $rows, 'filter' => $filter, 'user' => $adminUser]);
});

$router->get('/admin/reservation/{id}', function (array $p) use ($renderer, $repo, $adminUser) {
    $row = $repo->find((int) $p['id']);
    if ($row === null) {
        http_response_code(404);
        echo $renderer->render('not-found');
        return;
    }
    echo $renderer->render('detail', ['r' => $row, 'user' => $adminUser]);
});

$router->post('/admin/reservation/{id}/status', function (array $p) use ($repo, $db, $adminUser) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        echo 'csrf';
        return;
    }
    $status = (string) ($_POST['status'] ?? '');
    try {
        $repo->setStatus((int) $p['id'], $status);
        $secret = (string) \Kuko\Config::get('security.ip_hash_secret', '');
        $db->execStmt(
            'INSERT INTO admin_actions (admin_user, action, target_table, target_id, payload_json, ip_hash) VALUES (?,?,?,?,?,?)',
            [
                $adminUser,
                'set_status',
                'reservations',
                (int) $p['id'],
                json_encode(['status' => $status]),
                hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $secret),
            ]
        );
    } catch (\InvalidArgumentException) {
        http_response_code(400);
        echo 'bad status';
        return;
    }
    header('Location: /admin/reservation/' . (int) $p['id']);
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$match = $router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
if ($match === null) {
    http_response_code(404);
    echo $renderer->render('not-found');
    return;
}
($match->handler)($match->params ?? []);
