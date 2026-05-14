<?php
declare(strict_types=1);
namespace Kuko;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        self::ensureSession();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function verify(string $given): bool
    {
        self::ensureSession();
        if ($given === '' || empty($_SESSION[self::KEY])) return false;
        return hash_equals((string)$_SESSION[self::KEY], $given);
    }

    public static function reset(): void
    {
        unset($_SESSION[self::KEY]);
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (defined('TESTING')) {
                if (!isset($_SESSION)) {
                    $_SESSION = [];
                }
                return;
            }
            if (!headers_sent()) {
                session_start();
            }
        }
    }
}
