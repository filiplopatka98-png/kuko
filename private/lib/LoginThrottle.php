<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Brute-force throttle for admin login. Independent of RateLimit because
 * we must count only failures and reset on success (RateLimit counts every probe).
 */
final class LoginThrottle
{
    public function __construct(
        private string $dir,
        private int $max = 5,
        private int $windowSec = 3600,
    ) {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0700, true);
        }
    }

    public function permit(string $ip, string $username): bool
    {
        return $this->count($this->ipFile($ip)) < $this->max
            && $this->count($this->userFile($username)) < $this->max;
    }

    public function recordFailure(string $ip, string $username): void
    {
        $this->bump($this->ipFile($ip));
        $this->bump($this->userFile($username));
    }

    public function recordSuccess(string $ip, string $username): void
    {
        @unlink($this->ipFile($ip));
        @unlink($this->userFile($username));
    }

    private function ipFile(string $ip): string
    {
        return $this->dir . '/login_ip_' . sha1($ip) . '.json';
    }

    private function userFile(string $username): string
    {
        $u = strtolower(trim($username));
        return $this->dir . '/login_user_' . sha1($u === '' ? '(empty)' : $u) . '.json';
    }

    private function count(string $file): int
    {
        if (!is_file($file)) return 0;
        $s = json_decode((string) file_get_contents($file), true);
        if (!is_array($s) || !isset($s['start'], $s['count'])) return 0;
        if (time() - (int) $s['start'] >= $this->windowSec) return 0;
        return (int) $s['count'];
    }

    private function bump(string $file): void
    {
        $now = time();
        $s = ['start' => $now, 'count' => 0];
        if (is_file($file)) {
            $p = json_decode((string) file_get_contents($file), true);
            if (is_array($p) && isset($p['start'], $p['count']) && $now - (int) $p['start'] < $this->windowSec) {
                $s = $p;
            }
        }
        $s['count'] = (int) $s['count'] + 1;
        file_put_contents($file, json_encode($s), LOCK_EX);
    }
}
