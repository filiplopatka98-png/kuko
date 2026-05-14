<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\RateLimit;
use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kuko-rl-' . uniqid();
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($this->dir);
        }
    }

    public function testAllowsUnderLimit(): void
    {
        $rl = new RateLimit($this->dir, max: 3, windowSec: 3600);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($rl->allow(str_repeat('a', 64), 'res'));
        }
    }

    public function testBlocksOverLimit(): void
    {
        $rl = new RateLimit($this->dir, max: 2, windowSec: 3600);
        $h = str_repeat('b', 64);
        $rl->allow($h, 'res');
        $rl->allow($h, 'res');
        $this->assertFalse($rl->allow($h, 'res'));
    }

    public function testDifferentBucketsIndependent(): void
    {
        $rl = new RateLimit($this->dir, max: 1, windowSec: 3600);
        $h = str_repeat('c', 64);
        $this->assertTrue($rl->allow($h, 'res'));
        $this->assertTrue($rl->allow($h, 'contact'));
    }

    public function testRejectsInvalidHash(): void
    {
        $rl = new RateLimit($this->dir, max: 10, windowSec: 3600);
        $this->assertFalse($rl->allow('', 'res'));
    }
}
