<?php
declare(strict_types=1);

require dirname(__DIR__) . '/private/lib/App.php';
\Kuko\App::bootstrap();

use Kuko\Router;
use Kuko\Renderer;

$renderer = new Renderer(APP_ROOT . '/private/templates');
$router   = new Router();

$router->get('/', function () use ($renderer) {
    echo $renderer->render('pages/home');
});

$router->get('/ochrana-udajov', function () use ($renderer) {
    echo $renderer->render('pages/privacy');
});

$router->get('/rezervacia/{token}', function (array $p) use ($renderer) {
    try {
        $db = \Kuko\Db::fromConfig();
    } catch (\Throwable $e) {
        http_response_code(500);
        echo $renderer->render('pages/404');
        return;
    }
    $repo = new \Kuko\ReservationRepo($db);
    $token = preg_replace('/[^a-f0-9]/', '', (string) $p['token']);
    $row = $token === '' ? null : $repo->findByToken($token);
    if ($row === null) {
        http_response_code(404);
        echo $renderer->render('pages/404');
        return;
    }
    echo $renderer->render('pages/reservation-status', ['r' => $row]);
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$match = $router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);

if ($match === null) {
    http_response_code(404);
    echo $renderer->render('pages/404');
    return;
}

($match->handler)($match->params);
