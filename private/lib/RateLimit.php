<?php
declare(strict_types=1);
namespace Kuko;

final class RateLimit
{
    public function __construct(
        private string $dir,
        private int $max = 3,
        private int $windowSec = 3600,
    ) {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }

    public function allow(string $ipHash, string $bucket): bool
    {
        $safeHash = preg_replace('/[^a-f0-9]/i', '', $ipHash);
        $safeBucket = preg_replace('/[^a-z0-9_]/i', '', $bucket);
        if ($safeHash === '' || $safeBucket === '') return false;
        $file = $this->dir . '/' . $safeHash . '.' . $safeBucket . '.json';
        $now = time();
        $state = ['start' => $now, 'count' => 0];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            $parsed = $raw === false ? null : json_decode($raw, true);
            if (is_array($parsed) && isset($parsed['start'], $parsed['count']) && $now - (int)$parsed['start'] < $this->windowSec) {
                $state = $parsed;
            }
        }
        if ((int)$state['count'] >= $this->max) return false;
        $state['count'] = (int)$state['count'] + 1;
        file_put_contents($file, json_encode($state), LOCK_EX);
        return true;
    }
}
