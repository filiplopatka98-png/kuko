<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class RecaptchaBadgeHiddenTest extends TestCase
{
    public function testBadgeHiddenWithRequiredAttribution(): void
    {
        $root = \dirname(__DIR__, 3);
        $css = file_get_contents($root . '/public/assets/css/rezervacia.css');
        $tpl = file_get_contents($root . '/private/templates/pages/reservation.php');

        // Badge hidden via visibility.
        $this->assertMatchesRegularExpression(
            '/\.grecaptcha-badge\s*\{[^}]*visibility:\s*hidden/',
            $css
        );
        // Google's terms REQUIRE the attribution text + Privacy/Terms links
        // whenever the badge is hidden — rendered only when a site key exists.
        $this->assertStringContainsString('class="recaptcha-tos"', $tpl);
        $this->assertStringContainsString('https://policies.google.com/privacy', $tpl);
        $this->assertStringContainsString('https://policies.google.com/terms', $tpl);
        $this->assertMatchesRegularExpression('/if\s*\(\$siteKey\):[\s\S]*recaptcha-tos/', $tpl);
    }
}
