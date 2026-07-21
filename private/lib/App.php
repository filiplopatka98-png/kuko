<?php
declare(strict_types=1);
namespace Kuko;

final class App
{
    public static function bootstrap(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2));
        }
        require_once APP_ROOT . '/private/lib/autoload.php';
        if (!Config::isLoaded()) {
            Config::load(APP_ROOT . '/config/config.php');
        }
        date_default_timezone_set(Config::get('app.tz', 'Europe/Bratislava'));
        // display_errors is NEVER on in production, even if debug was left true
        // by mistake — a stray fatal must not leak paths/SQL to visitors.
        $isProd = Config::get('app.env', 'production') === 'production';
        if (Config::get('app.debug', false) && !$isProd) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED);
            ini_set('display_errors', '0');
        }

        // Session cookie hardening — runs ONCE at bootstrap, before any
        // session_start() elsewhere (Auth/Csrf start sessions lazily). The
        // CLI/TESTING guard makes this a complete no-op under PHPUnit, and
        // session_status()/headers_sent() guards ensure we never fight an
        // already-started session or trigger "headers already sent".
        //
        // Prod sits behind the WebSupport reverse proxy which terminates TLS
        // and forwards X-Forwarded-Proto: https (same header the .htaccess
        // force-HTTPS rule keys on). So cookie_secure is only set when https
        // is detected — local plain-http dev keeps working.
        if (PHP_SAPI !== 'cli' && !defined('TESTING') && session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $https = self::isHttps();
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_samesite', 'Lax');
            if ($https) { @ini_set('session.cookie_secure', '1'); }
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $https,
            ]);
        }
    }

    /**
     * Canonical HTTPS detection — single source of truth for session/cookie
     * "secure" flags across the app. Behind the WebSupport reverse proxy TLS is
     * terminated at the edge and forwarded as X-Forwarded-Proto: https, so the
     * bare $_SERVER['HTTPS'] check alone under-reports and would drop the
     * Secure attribute on production cookies.
     */
    public static function isHttps(): bool
    {
        return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off');
    }
}
