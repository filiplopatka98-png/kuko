<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Session-based admin auth.
 *
 * Credentials come from config/.htpasswd so the file format from
 * the previous Basic Auth deploy is reused — one user per line, bcrypt hashed:
 *
 *   username:$2y$05$.....
 *
 * Plus a "remember me" cookie that stores a signed identity token valid
 * for 30 days, validated by HMAC against `auth.secret`.
 */
final class Auth
{
    private const SESS_USER     = '_admin_user';
    private const SESS_LOGIN_AT = '_admin_login_at'; // absolute-timeout anchor
    private const SESS_LAST     = '_admin_last';     // idle-timeout marker
    private const COOKIE_NAME   = 'kuko_admin';
    private const COOKIE_TTL    = 30 * 86400;

    /** Seconds of inactivity after which the session is dropped. */
    private static function idleTtl(): int
    {
        return (int) Config::get('admin.idle_timeout', 8 * 3600);
    }

    /** Hard cap on session age regardless of activity. */
    private static function absoluteTtl(): int
    {
        return (int) Config::get('admin.absolute_timeout', 24 * 3600);
    }

    public static function user(): ?string
    {
        self::ensureSession();
        $now = time();

        if (!empty($_SESSION[self::SESS_USER])) {
            $loginAt = (int) ($_SESSION[self::SESS_LOGIN_AT] ?? 0);
            $lastAt  = (int) ($_SESSION[self::SESS_LAST] ?? 0);
            $absoluteExpired = $loginAt > 0 && ($now - $loginAt) > self::absoluteTtl();
            $idleExpired     = $lastAt  > 0 && ($now - $lastAt)  > self::idleTtl();
            if ($absoluteExpired || $idleExpired) {
                // Session too old / idle — drop the admin identity, then fall
                // through to the remember-me cookie (which may re-establish it).
                self::clearSessionIdentity();
            } else {
                $_SESSION[self::SESS_LAST] = $now; // slide the idle window
                return (string) $_SESSION[self::SESS_USER];
            }
        }

        // Try remember-me cookie: user|iat|sig. The HMAC binds the issue time
        // (so a stolen cookie expires server-side) and the current password
        // fingerprint (so a password change invalidates every old cookie).
        $cookie = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        $user = self::rememberCookieValid($cookie, $now);
        if ($user === null) return null;

        // Re-establish a fresh session from the trusted cookie.
        session_regenerate_id(true);
        $_SESSION[self::SESS_USER]     = $user;
        $_SESSION[self::SESS_LOGIN_AT] = $now;
        $_SESSION[self::SESS_LAST]     = $now;
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
        $now = time();
        $_SESSION[self::SESS_USER]     = $user;
        $_SESSION[self::SESS_LOGIN_AT] = $now;
        $_SESSION[self::SESS_LAST]     = $now;

        if ($remember) {
            $iat = $now;
            setcookie(self::COOKIE_NAME, $user . '|' . $iat . '|' . self::sign($user, $iat), [
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
        self::clearSessionIdentity();
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
        $file = APP_ROOT . '/config/.htpasswd';
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

    /**
     * Validate a remember-me cookie string without touching the session.
     * Returns the username when the cookie is authentic and unexpired, else
     * null. Pure (no session/cookie side effects) so it is unit-testable.
     *
     * Fail-closed on an empty auth.secret: an HMAC keyed on '' is trivially
     * forgeable, so a remember-me cookie must never be trusted in that case.
     * Session-based login never reaches this path, so it is unaffected.
     */
    public static function rememberCookieValid(string $cookie, int $now): ?string
    {
        if ($cookie === '') return null;
        if ((string) Config::get('auth.secret', '') === '') return null; // fail-closed
        [$user, $iat, $sig] = array_pad(explode('|', $cookie, 3), 3, '');
        if ($user === '' || $iat === '' || $sig === '') return null;
        if (!ctype_digit($iat)) return null;
        if ($now - (int) $iat > self::COOKIE_TTL) return null; // expired
        if (!hash_equals(self::sign($user, (int) $iat), $sig)) return null;
        if (!self::userExists($user)) return null;
        return $user;
    }

    private static function sign(string $user, int $iat): string
    {
        $secret = (string) Config::get('auth.secret', '');
        // Bind the current password fingerprint: changing the password rewrites
        // the stored bcrypt hash, which changes this fingerprint, which makes
        // every previously issued remember-me signature stop verifying.
        $fp = self::passwordFingerprint($user);
        return hash_hmac('sha256', 'admin|' . $user . '|' . $iat . '|' . $fp, $secret);
    }

    /**
     * Short, non-reversible fingerprint of the user's current bcrypt hash.
     * Empty string for an unknown user (keeps sign() total).
     */
    private static function passwordFingerprint(string $user): string
    {
        $hash = self::loadHtpasswd()[$user] ?? '';
        return substr(sha1($hash), 0, 16);
    }

    /** Drop only the admin-identity keys (keeps CSRF token / flash intact). */
    private static function clearSessionIdentity(): void
    {
        unset(
            $_SESSION[self::SESS_USER],
            $_SESSION[self::SESS_LOGIN_AT],
            $_SESSION[self::SESS_LAST]
        );
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
        return App::isHttps();
    }
}
