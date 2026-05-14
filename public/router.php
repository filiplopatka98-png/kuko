<?php
// Dev-only router used with: php -S 127.0.0.1:8000 -t public public/router.php
// Mirrors the .htaccess front-controller rewrites for the built-in server.
// In production these rewrites are handled by Apache + the per-directory .htaccess files.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// /api/* → execute the matching api/*.php script
if ($path === '/api/reservation') {
    require __DIR__ . '/api/reservation.php';
    return;
}

// /admin/* → admin entry point (NOTE: dev mode bypasses Basic Auth)
if ($path === '/admin' || $path === '/admin/' || str_starts_with($path, '/admin/')) {
    // emulate Basic Auth user for dev convenience
    $_SERVER['PHP_AUTH_USER'] = $_SERVER['PHP_AUTH_USER'] ?? 'dev';
    require __DIR__ . '/admin/index.php';
    return;
}

// Real file? Let the built-in server serve it (CSS/JS/img/fonts).
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
