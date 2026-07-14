<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\ClientIp;
use Kuko\Config;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        Config::reset();
    }

    private function config(bool $trustProxy): void
    {
        Config::reset();
        Config::load(__DIR__ . '/../fixtures/config.test.php');
        // fixture has no security.trust_proxy → get() default fallback applies;
        // simulate the two states by writing a throwaway config file per case.
        $tmp = tempnam(sys_get_temp_dir(), 'cfg');
        file_put_contents($tmp, "<?php return ['security' => ['trust_proxy' => " . ($trustProxy ? 'true' : 'false') . "]];");
        Config::reset();
        Config::load($tmp);
        @unlink($tmp);
    }

    public function testDefaultUsesRemoteAddrIgnoringHeader(): void
    {
        $this->config(false);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        $this->assertSame('203.0.113.7', ClientIp::get());
    }

    public function testTrustProxyUsesFirstForwardedHop(): void
    {
        $this->config(true);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9, 10.0.0.1';
        $this->assertSame('198.51.100.9', ClientIp::get());
    }

    public function testTrustProxyFallsBackOnGarbageHeader(): void
    {
        $this->config(true);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
        $this->assertSame('10.0.0.2', ClientIp::get());
    }
}
