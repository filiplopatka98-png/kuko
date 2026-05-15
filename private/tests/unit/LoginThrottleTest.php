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

    public function testPerUsernameBlockSpansIps(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        for ($i = 0; $i < 5; $i++) { $t->permit('10.0.0.' . $i, 'victim'); $t->recordFailure('10.0.0.' . $i, 'victim'); }
        $this->assertFalse($t->permit('10.0.0.99', 'victim'), 'username bucket blocks even from a fresh IP');
    }
}
