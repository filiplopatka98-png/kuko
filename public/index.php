<?php
declare(strict_types=1);

require dirname(__DIR__) . '/private/lib/App.php';
\Kuko\App::bootstrap();

use Kuko\Router;
use Kuko\Renderer;
use Kuko\Maintenance;

$renderer = new Renderer(APP_ROOT . '/private/templates');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Maintenance gate runs before the router. The POST handler at /maintenance is
// allowed through so visitors can submit the staff password.
$isMaintenancePost = $method === 'POST' && rtrim($path, '/') === '/maintenance';
if (Maintenance::enabled() && !Maintenance::shouldBypass($path) && !$isMaintenancePost) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo $renderer->render('pages/maintenance');
    return;
}

$router = new Router();

$router->get('/', function () use ($renderer) {
    echo $renderer->render('pages/home');
});

$router->get('/robots.txt', function () {
    header('Content-Type: text/plain; charset=utf-8');
    $indexing = (bool) \Kuko\Config::get('app.public_indexing', false);
    if ($indexing) {
        echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /rezervacia/\n\nSitemap: " . rtrim((string) \Kuko\Config::get('app.url'), '/') . "/sitemap.xml\n";
    } else {
        echo "User-agent: *\nDisallow: /\n";
    }
});

$router->get('/sitemap.xml', function () {
    header('Content-Type: application/xml; charset=utf-8');
    $indexing = (bool) \Kuko\Config::get('app.public_indexing', false);
    $base = rtrim((string) \Kuko\Config::get('app.url'), '/');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    if ($indexing) {
        $today = date('Y-m-d');
        foreach ([
            ['/',                 '1.0', 'monthly'],
            ['/rezervacia',       '0.9', 'weekly'],
            ['/ochrana-udajov',   '0.3', 'yearly'],
        ] as [$url, $priority, $freq]) {
            echo "  <url>\n    <loc>{$base}{$url}</loc>\n    <lastmod>{$today}</lastmod>\n    <changefreq>{$freq}</changefreq>\n    <priority>{$priority}</priority>\n  </url>\n";
        }
    }
    echo "</urlset>\n";
});

$router->get('/ochrana-udajov', function () use ($renderer) {
    echo $renderer->render('pages/privacy');
});

$router->get('/rezervacia', function () use ($renderer) {
    try {
        $db = \Kuko\Db::fromConfig();
        $packages = (new \Kuko\PackagesRepo($db))->listActive();
    } catch (\Throwable) {
        $packages = [];
    }
    echo $renderer->render('pages/reservation', ['packages' => $packages]);
});

$router->get('/rezervacia/{token}', function (array $p) use ($renderer) {
    try {
        $db = \Kuko\Db::fromConfig();
    } catch (\Throwable) {
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

// Maintenance bypass form submit
$router->post('/maintenance', function () use ($renderer) {
    $given = (string) ($_POST['password'] ?? '');
    if (Maintenance::passwordMatches($given)) {
        Maintenance::grantStaffCookie();
        header('Location: /');
        return;
    }
    http_response_code(401);
    echo $renderer->render('pages/maintenance', ['error' => true]);
});

$match = $router->match($method, $path);

if ($match === null) {
    http_response_code(404);
    echo $renderer->render('pages/404');
    return;
}

($match->handler)($match->params);
