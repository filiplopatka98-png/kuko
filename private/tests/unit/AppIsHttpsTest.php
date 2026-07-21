<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use Kuko\App;
use PHPUnit\Framework\TestCase;

/**
 * Ú10: shared HTTPS detector must honour the reverse-proxy header, so session
 * cookies keep the Secure attribute behind the WebSupport TLS-terminating
 * proxy (where $_SERVER['HTTPS'] is unset).
 */
final class AppIsHttpsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function testDetectsDirectHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(App::isHttps());
    }

    public function testDetectsForwardedProto(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(App::isHttps());
    }

    public function testPlainHttpIsFalse(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $this->assertFalse(App::isHttps());
    }

    public function testHttpsOffIsFalse(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse(App::isHttps());
    }

    public function testReservationApiUsesSharedDetector(): void
    {
        $src = file_get_contents(\dirname(__DIR__, 3) . '/public/api/reservation.php');
        $this->assertStringContainsString('App::isHttps()', $src);
        $this->assertStringNotContainsString("'secure'   => isset(\$_SERVER['HTTPS'])", $src);
    }
}
