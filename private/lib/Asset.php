<?php
declare(strict_types=1);
namespace Kuko;

final class Asset
{
    public static function stamp(string $path, string $docRoot): string
    {
        $q = strpos($path, '?');
        if ($q === false) {
            $pathPart = $path;
            $query = '';
        } else {
            $pathPart = substr($path, 0, $q);
            $query = substr($path, $q + 1);
        }

        // Prefer a committed minified sibling (e.g. main.css -> main.min.css) when
        // present. Only rewrite if the requested path is not already a *.min.<ext>.
        $ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION));
        if ($ext !== '' && substr($pathPart, -strlen('.min.' . $ext)) !== '.min.' . $ext) {
            $minPath = substr($pathPart, 0, -strlen('.' . $ext)) . '.min.' . $ext;
            if (is_file($docRoot . $minPath)) {
                $pathPart = $minPath;
            }
        }

        $fsPath = $docRoot . $pathPart;
        if (!is_file($fsPath)) {
            return $path;
        }

        $mtime = @filemtime($fsPath);
        if ($mtime === false) {
            return $path;
        }

        return $query === ''
            ? $pathPart . '?v=' . $mtime
            : $pathPart . '?' . $query . '&v=' . $mtime;
    }

    public static function url(string $path): string
    {
        return self::stamp($path, self::docRoot());
    }

    private static function docRoot(): string
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot !== '' && is_dir($docRoot)) {
            return $docRoot;
        }

        if (defined('APP_ROOT')) {
            $appRoot = (string) APP_ROOT;
            if (is_dir($appRoot . '/public')) {
                return $appRoot . '/public';
            }
            if (is_dir($appRoot . '/web')) {
                return $appRoot . '/web';
            }
            return $appRoot . '/public';
        }

        return '';
    }
}
