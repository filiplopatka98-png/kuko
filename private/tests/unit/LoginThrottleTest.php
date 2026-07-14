<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use Kuko\LoginThrottle;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kuko-throttle-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }
    }

    public function testAllowsUpToFiveBadAttemptsThenBlocks(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($t->permit('1.2.3.4', 'admin'), "attempt $i should be permitted");
            $t->recordFailure('1.2.3.4', 'admin');
        }
        $this->assertFalse($t->permit('1.2.3.4', 'admin'), '6th attempt blocked by IP bucket');
    }

    public function testSuccessClearsBuckets(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        for ($i = 0; $i < 5; $i++) { $t->permit('9.9.9.9', 'bob'); $t->recordFailure('9.9.9.9', 'bob'); }
        $this->assertFalse($t->permit('9.9.9.9', 'bob'));
        $t->recordSuccess('9.9.9.9', 'bob');
        $this->assertTrue($t->permit('9.9.9.9', 'bob'), 'buckets cleared after success');
    }

    /**
     * Regression (account-lockout DoS): a stranger spamming the real admin's
     * username from THEIR own IP must NOT lock the admin out from a different
     * IP. The username counter is scoped per-IP, so the legit IP stays open.
     */
    public function testStrangerCannotLockOutAdminFromAnotherIp(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        for ($i = 0; $i < 10; $i++) { $t->recordFailure('66.66.66.66', 'victim'); }
        $this->assertFalse($t->permit('66.66.66.66', 'victim'), 'attacker IP is locked');
        $this->assertTrue($t->permit('10.0.0.99', 'victim'), 'legit admin on a fresh IP stays permitted');
    }

    public function testStaleBucketResetsAfterWindow(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        // Write a maxed-out bucket whose window already elapsed.
        $stale = ['start' => time() - 3601, 'count' => 5];
        file_put_contents($this->dir . '/login_ip_' . sha1('5.5.5.5') . '.json', json_encode($stale));
        file_put_contents($this->dir . '/login_user_' . sha1('joe') . '.json', json_encode($stale));
        $this->assertTrue($t->permit('5.5.5.5', 'joe'), 'stale (expired-window) buckets must reset to 0');
    }
}
