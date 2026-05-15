<?php
declare(strict_types=1);
namespace Kuko;

final class Seo
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
            error_log('[Seo] settings DB unavailable: ' . $e->getMessage());
            return null;
        }
        return self::$settings;
    }

    /**
     * Read a settings key, swallowing any DB error (table missing, query fails).
     * SEO rendering must never crash on a settings-layer fault — it degrades to
     * the passed-in fallback instead of fataling.
     */
    private static function settingValue(string $key): ?string
    {
        try {
            return self::settings()?->get($key);
        } catch (\Throwable $e) {
            error_log('[Seo] settings read failed for "' . $key . '": ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve SEO meta with DB override and hardcoded fallback.
     * @return array{title:string,description:string,robots:string}
     */
    public static function resolve(?string $pageType, string $titleFallback, string $descFallback, bool $globalIndexing, ?bool $pageIndexing): array
    {
        $pt = $pageType ?? 'default';
        $title = self::pick("seo.$pt.title", $titleFallback);
        $desc  = self::pick("seo.$pt.description", $descFallback);
        $idxVal = self::settingValue('seo.public_indexing');
        $global = $idxVal !== null ? ($idxVal === '1') : $globalIndexing;
        $index = $pageIndexing ?? $global;
        return ['title' => $title, 'description' => $desc, 'robots' => $index ? 'index, follow' : 'noindex, nofollow'];
    }

    private static function pick(string $key, string $fallback): string
    {
        $v = self::settingValue($key);
        return ($v !== null && $v !== '') ? $v : $fallback;
    }
}
