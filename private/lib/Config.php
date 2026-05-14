<?php
declare(strict_types=1);
namespace Kuko;

final class Config
{
    private static ?array $data = null;

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config file not found: $path");
        }
        self::$data = require $path;
    }

    public static function reset(): void { self::$data = null; }

    public static function isLoaded(): bool { return self::$data !== null; }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) {
            throw new \RuntimeException('Config not loaded');
        }
        $parts = explode('.', $key);
        $value = self::$data;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                if (func_num_args() < 2) {
                    throw new \RuntimeException("Config key missing: $key");
                }
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }
}
