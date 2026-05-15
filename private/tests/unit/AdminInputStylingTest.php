<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class AdminInputStylingTest extends TestCase
{
    private string $css;
    protected function setUp(): void { $this->css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/admin.css'); }
    public function testGlobalAdminInputRuleExists(): void
    {
        // a rule scoping all admin-main controls with the consistent border-radius
        $this->assertMatchesRegularExpression('/\.admin-main[^{]*(input|select|textarea)[^{]*\{[^}]*border-radius/s', $this->css);
    }
    public function testGlobalAdminInputFocusRing(): void
    {
        $this->assertMatchesRegularExpression('/\.admin-main[^{]*:focus[^{]*\{[^}]*box-shadow:\s*0 0 0 3px rgba\(216,\s*139,\s*190/s', $this->css);
    }
    public function testCheckboxesNotForceStyled(): void
    {
        $this->assertStringContainsString(':not([type=checkbox])', $this->css);
    }
}
