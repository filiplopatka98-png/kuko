<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Session-based admin auth.
 *
 * Credentials come from public/admin/.htpasswd so the file format from
 * the previous Basic Auth deploy is reused — one user per line, bcrypt hashed:
 *
 *   username:$2y$05$.....
 *
 * Plus a "remember me" cookie that stores a signed identity token valid
 * for 30 days, validated by HMAC against `auth.secret`.
 */
final class Auth
{
    private const SESS_USER    = '_admin_user';
    private const SESS_AT      = '_admin_at';
    private const COOKIE_NAME  = 'kuko_admin';
    private const COOKIE_TTL   = 30 * 86400;

    public static function user(): ?string
    {
        self::ensureSession();
        if (!empty($_SESSION[self::SESS_USER])) {
            return (string) $_SESSION[self::SESS_USER];
        }
        // Try remember-me cookie
        $cookie = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        if ($cookie === '') return null;
        [$user, $sig] = array_pad(explode('|', $cookie, 2), 2, '');
        if ($user === '' || $sig === '') return null;
        if (!hash_equals(self::sign($user), $sig)) return null;
        if (!self::userExists($user)) return null;
        // Re-establish session
        $_SESSION[self::SESS_USER] = $user;
        $_SESSION[self::SESS_AT]   = time();
        return $user;
    }

    public static function isAuthenticated(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $user, string $password, bool $remember = false): bool
    {
        $entries = self::loadHtpasswd();
        if (!isset($entries[$user])) {
            // Constant-time-ish: hash anyway to avoid timing leak
            password_verify($password, '$2y$05$' . str_repeat('a', 53));
            return false;
        }
        if (!password_verify($password, $entries[$user])) return false;

        self::ensureSession();
        session_regenerate_id(true);
        $_SESSION[self::SESS_USER] = $user;
        $_SESSION[self::SESS_AT]   = time();

        if ($remember) {
            setcookie(self::COOKIE_NAME, $user . '|' . self::sign($user), [
                'expires'  => time() + self::COOKIE_TTL,
                'path'     => '/',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return true;
    }

    public static function logout(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESS_USER], $_SESSION[self::SESS_AT]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function requireLogin(): void
    {
        if (self::isAuthenticated()) return;
        $next = $_SERVER['REQUEST_URI'] ?? '/admin';
        header('Location: /admin/login?next=' . rawurlencode($next));
        exit;
    }

    /** @return array<string,string> username => bcrypt hash */
    private static function loadHtpasswd(): array
    {
        $file = APP_ROOT . '/public/admin/.htpasswd';
        if (!is_file($file)) return [];
        $entries = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$u, $h] = array_pad(explode(':', trim($line), 2), 2, '');
            if ($u !== '' && $h !== '') $entries[$u] = $h;
        }
        return $entries;
    }

    private static function userExists(string $user): bool
    {
        return isset(self::loadHtpasswd()[$user]);
    }

    private static function sign(string $user): string
    {
        $secret = (string) Config::get('auth.secret', '');
        return hash_hmac('sha256', 'admin|' . $user, $secret);
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    private static function isHttps(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
