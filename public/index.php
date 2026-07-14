<?php
declare(strict_types=1);

require dirname(__DIR__) . '/private/lib/App.php';
\Kuko\App::bootstrap();

use Kuko\Router;
use Kuko\Renderer;
use Kuko\Maintenance;

\Kuko\Csp::send('public');

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

// Public indexing: DB setting (/admin/seo toggle) wins; config is the fallback
// so robots.txt / sitemap.xml flip live with the SEO editor and the <meta robots> tag.
$publicIndexing = static function (): bool {
    try {
        $v = (new \Kuko\SettingsRepo(\Kuko\Db::fromConfig()))->get('seo.public_indexing');
        if ($v !== null) return $v === '1';
    } catch (\Throwable $e) {
        error_log('[robots] seo.public_indexing read failed: ' . $e->getMessage());
    }
    return (bool) \Kuko\Config::get('app.public_indexing', false);
};

$router->get('/', function () use ($renderer) {
    $gallery  = [];
    $packages = [];
    try {
        $db = \Kuko\Db::fromConfig();
        $gallery  = (new \Kuko\MediaRepo($db, \Kuko\Asset::docRoot() . '/assets/img/gallery'))->homepageSet();
        $packages = (new \Kuko\PackagesRepo($db))->listActive();
    } catch (\Throwable $e) {
        error_log('[home] gallery/packages load failed: ' . $e->getMessage());
    }
    echo $renderer->render('pages/home', ['gallery' => $gallery, 'packages' => $packages]);
});

$router->get('/robots.txt', function () use ($publicIndexing) {
    header('Content-Type: text/plain; charset=utf-8');
    $indexing = $publicIndexing();
    if ($indexing) {
        $base = rtrim((string) \Kuko\Config::get('app.url'), '/');
        echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /rezervacia/\n\n"
           . "Sitemap: $base/sitemap.xml\n"
           . "# LLM-friendly site brief (llmstxt.org convention)\n"
           . "LLM-Content: $base/llms.txt\n";
    } else {
        echo "User-agent: *\nDisallow: /\n";
    }
});

// LLM-friendly Markdown brief. Dynamically generated from the same Content
// + PackagesRepo sources as the homepage, so edits in /admin propagate here
// on the next request. Gated by the public-indexing toggle just like robots.
$router->get('/llms.txt', function () use ($publicIndexing) {
    header('Content-Type: text/markdown; charset=utf-8');
    if (!$publicIndexing()) {
        http_response_code(404);
        echo "Not Found\n";
        return;
    }
    $db = null;
    try { $db = \Kuko\Db::fromConfig(); } catch (\Throwable $e) {
        error_log('[llms.txt] DB unavailable, using defaults: ' . $e->getMessage());
    }
    echo \Kuko\LlmsTxt::render($db);
});

$router->get('/sitemap.xml', function () use ($publicIndexing) {
    header('Content-Type: application/xml; charset=utf-8');
    $indexing = $publicIndexing();
    $base = rtrim((string) \Kuko\Config::get('app.url'), '/');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    if ($indexing) {
        $today = date('Y-m-d');
        foreach ([
            ['/',                 '1.0', 'monthly'],
            ['/rezervacia',       '0.9', 'weekly'],
            ['/galeria',          '0.6', 'monthly'],
            ['/faq',              '0.5', 'monthly'],
            ['/ochrana-udajov',   '0.3', 'yearly'],
            ['/zasady-cookies',   '0.3', 'yearly'],
        ] as [$url, $priority, $freq]) {
            echo "  <url>\n    <loc>{$base}{$url}</loc>\n    <lastmod>{$today}</lastmod>\n    <changefreq>{$freq}</changefreq>\n    <priority>{$priority}</priority>\n  </url>\n";
        }
    }
    echo "</urlset>\n";
});

$router->get('/ochrana-udajov', function () use ($renderer) {
    echo $renderer->render('pages/privacy');
});

$router->get('/zasady-cookies', function () use ($renderer) {
    echo $renderer->render('pages/cookies');
});

$router->get('/faq', function () use ($renderer) {
    echo $renderer->render('pages/faq');
});

$router->get('/galeria', function () use ($renderer) {
    $gallery = [];
    try {
        $db = \Kuko\Db::fromConfig();
        $gallery = (new \Kuko\MediaRepo($db, \Kuko\Asset::docRoot() . '/assets/img/gallery'))->listVisible();
    } catch (\Throwable $e) {
        error_log('[galeria] gallery load failed: ' . $e->getMessage());
    }
    echo $renderer->render('pages/gallery', ['gallery' => $gallery]);
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
    // Throttle password guessing on the staff bypass (per IP).
    $secret = (string) \Kuko\Config::get('security.ip_hash_secret', '');
    $ipHash = hash('sha256', \Kuko\ClientIp::get() . '|' . $secret);
    $rl = new \Kuko\RateLimit(APP_ROOT . '/private/logs/rate', 10);
    if (!$rl->allow($ipHash, 'maintenance')) {
        http_response_code(429);
        header('Retry-After: 3600');
        echo $renderer->render('pages/maintenance', ['error' => true]);
        return;
    }
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
