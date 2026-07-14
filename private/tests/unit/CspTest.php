<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Csp;
use PHPUnit\Framework\TestCase;

final class CspTest extends TestCase
{
    protected function tearDown(): void
    {
        Csp::reset();
    }

    public function testNonceIsStableWithinRequestAndHex(): void
    {
        $a = Csp::nonce();
        $b = Csp::nonce();
        $this->assertSame($a, $b, 'nonce is cached per request');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a);
    }

    public function testResetGivesFreshNonce(): void
    {
        $a = Csp::nonce();
        Csp::reset();
        $this->assertNotSame($a, Csp::nonce());
    }

    public function testAdminPolicyHasNonceAndNoUnsafeInlineScript(): void
    {
        $p = Csp::policy('admin');
        $this->assertStringContainsString("script-src 'self' 'nonce-" . Csp::nonce() . "'", $p);
        // script-src must NOT allow unsafe-inline anymore.
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $p);
        $this->assertStringContainsString("frame-ancestors 'self'", $p);
    }

    public function testPublicPolicyKeepsThirdPartyAllowlistWithoutUnsafeInlineScript(): void
    {
        $p = Csp::policy('public');
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $p);
        $this->assertStringContainsString('https://www.google.com/recaptcha/', $p);
        $this->assertStringContainsString('https://unpkg.com', $p);
        $this->assertStringContainsString("'nonce-" . Csp::nonce() . "'", $p);
    }
}
