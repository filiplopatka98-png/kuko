<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
