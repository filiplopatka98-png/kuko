<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
if (!defined('TESTING')) {
    define('TESTING', true);
}

require APP_ROOT . '/private/lib/autoload.php';
