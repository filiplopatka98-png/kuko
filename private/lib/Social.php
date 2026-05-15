<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Social media URLs, DB-preferred with config fallback.
 *
 * The /admin/contact editor writes social.facebook / social.instagram into
 * the settings table. The frontend prefers those values but degrades to the
 * config.php value (and finally the passed-in fallback) so the site never
 * breaks when the DB is unavailable.
 */
final class Social
{
    private static ?SettingsRepo $settings = null;
    private static bool $triedConfig = false;

    public static function setSettings(?SettingsRepo $s): void
    {
        self::$settings = $s;
        self::$triedConfig = false;
    }

    private static function settings(): ?SettingsRepo
    {
        if (self::$settings !== null) return self::$settings;
        if (self::$triedConfig) return null;
        self::$triedConfig = true;
        try {
            self::$settings = new SettingsRepo(Db::fromConfig());
        } catch (\Throwable $e) {
            error_log('[Social] settings DB unavailable: ' . $e->getMessage());
            return null;
        }
        return self::$settings;
    }

    /**
     * Resolve a social URL. Order: settings table → config.php → passed fallback.
     * Never throws — a DB fault degrades to config / fallback.
     */
    public static function url(string $network, string $fallback = ''): string
    {
        $key = 'social.' . $network;
        try {
            $v = self::settings()?->get($key);
            if ($v !== null && $v !== '') return $v;
        } catch (\Throwable $e) {
            error_log('[Social] settings read failed for "' . $key . '": ' . $e->getMessage());
        }
        $cfg = (string) Config::get($key, '');
        return $cfg !== '' ? $cfg : $fallback;
    }
}
