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
        if (Config::get('app.debug', false)) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED);
            ini_set('display_errors', '0');
        }
    }
}
