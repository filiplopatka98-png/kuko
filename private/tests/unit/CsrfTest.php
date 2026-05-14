<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Csrf::reset();
    }

    public function testTokenIsString64Hex(): void
    {
        $t = Csrf::token();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $t);
    }

    public function testTokenIsStable(): void
    {
        $a = Csrf::token();
        $b = Csrf::token();
        $this->assertSame($a, $b);
    }

    public function testVerifyAccepts(): void
    {
        $t = Csrf::token();
        $this->assertTrue(Csrf::verify($t));
    }

    public function testVerifyRejectsWrong(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::verify(str_repeat('0', 64)));
    }

    public function testVerifyRejectsEmpty(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::verify(''));
    }

    public function testResetDropsToken(): void
    {
        Csrf::token();
        Csrf::reset();
        $this->assertEmpty($_SESSION['_csrf_token'] ?? '');
    }
}
