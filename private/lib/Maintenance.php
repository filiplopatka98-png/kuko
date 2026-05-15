<?php
declare(strict_types=1);
namespace Kuko;

final class Maintenance
{
    private const COOKIE_NAME = 'kuko_staff';
    private const COOKIE_TTL  = 30 * 86400; // 30 days

    private static ?SettingsRepo $settings = null;

    public static function setSettings(?SettingsRepo $s): void
    {
        self::$settings = $s;
    }

    private static function settings(): ?SettingsRepo
    {
        if (self::$settings !== null) return self::$settings;
        try {
            self::$settings = new SettingsRepo(Db::fromConfig());
        } catch (\Throwable) {
            return null;
        }
        return self::$settings;
    }

    /**
     * Maintenance password from settings (DB) with config fallback.
     * Returns '' when neither source defines one.
     */
    /**
     * Read a settings key, swallowing any DB error (table missing, query fails).
     * The maintenance gate must never crash on a settings-layer fault — it
     * degrades to the config fallback instead of fataling.
     */
    private static function settingValue(string $key): ?string
    {
        try {
            return self::settings()?->get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function password(): string
    {
        $fromSettings = self::settingValue('maintenance.password');
        if ($fromSettings !== null && $fromSettings !== '') {
            return $fromSettings;
        }
        return (string) Config::get('app.maintenance_password', '');
    }

    /** True if maintenance mode is enabled (settings DB, config fallback). */
    public static function enabled(): bool
    {
        $fromSettings = self::settingValue('maintenance.enabled');
        if ($fromSettings !== null) {
            return $fromSettings === '1';
        }
        return (bool) Config::get('app.maintenance', false);
    }

    /** True if visitor has already passed the staff bypass form. */
    public static function isStaff(): bool
    {
        $cookie = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        if ($cookie === '') return false;
        return hash_equals(self::expectedCookieValue(), $cookie);
    }

    public static function grantStaffCookie(): void
    {
        setcookie(self::COOKIE_NAME, self::expectedCookieValue(), [
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearStaffCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function passwordMatches(string $given): bool
    {
        $expected = self::password();
        if ($expected === '') return false;
        return hash_equals($expected, $given);
    }

    /** Should this incoming request bypass the maintenance page? */
    public static function shouldBypass(string $path): bool
    {
        if (self::isStaff()) return true;
        // Admin login flow needs to remain reachable so staff can recover the site.
        // /admin/login renders even in maintenance; everything else under /admin/ requires login anyway.
        if ($path === '/admin/login' || $path === '/admin/login/') return true;
        // Assets and the maintenance POST handler need to load.
        if (str_starts_with($path, '/assets/')) return true;
        if ($path === '/maintenance' || $path === '/maintenance/') return true;
        // SEO crawl files must remain reachable so robots see the indexing directive.
        if ($path === '/robots.txt' || $path === '/sitemap.xml') return true;
        return false;
    }

    private static function expectedCookieValue(): string
    {
        $password = self::password();
        $secret   = (string) Config::get('auth.secret', '');
        // Cookie value depends on both password and auth secret — rotating either invalidates all sessions.
        return hash('sha256', 'maintenance|' . $password . '|' . $secret);
    }

    private static function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
