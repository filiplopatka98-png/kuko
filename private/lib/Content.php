<?php
// private/lib/Content.php
declare(strict_types=1);
namespace Kuko;

final class Content
{
    private static ?Db $db = null;
    private static ?ContentBlocksRepo $repo = null;
    private static bool $triedConfig = false;

    public static function setDb(?Db $db): void
    {
        self::$db = $db;
        self::$repo = null;
        self::$triedConfig = false;
    }

    public static function reset(): void
    {
        self::$repo = null;
    }

    public static function get(string $key, string $fallback = ''): string
    {
        $value = self::lookup($key);
        $result = $value ?? $fallback;
        if (str_contains($result, '{{year}}')) {
            $result = str_replace('{{year}}', date('Y'), $result);
        }
        return $result;
    }

    private static function lookup(string $key): ?string
    {
        try {
            $repo = self::repo();
            if ($repo === null) return null;
            return $repo->get($key);
        } catch (\Throwable $e) {
            error_log('[Content] lookup failed for "' . $key . '": ' . $e->getMessage());
            return null;
        }
    }

    private static function repo(): ?ContentBlocksRepo
    {
        if (self::$repo !== null) return self::$repo;
        if (self::$db === null) {
            if (self::$triedConfig) return null;
            self::$triedConfig = true;
            try {
                self::$db = Db::fromConfig();
            } catch (\Throwable $e) {
                error_log('[Content] DB unavailable, serving fallbacks: ' . $e->getMessage());
                return null;
            }
        }
        if (self::$db === null) return null;
        self::$repo = new ContentBlocksRepo(self::$db);
        return self::$repo;
    }
}
