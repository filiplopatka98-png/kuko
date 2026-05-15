<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ReservationFormA11yTest extends TestCase
{
    private string $tpl;
    protected function setUp(): void { $this->tpl = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/pages/reservation.php'); }
    public function testRequiredInputsHaveAriaRequired(): void
    {
        // each of the 3 known required inputs must carry aria-required="true"
        foreach (['id="f-name"','id="f-phone"','id="f-email"'] as $needle) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]*' . preg_quote($needle, '/') . '[^>]*aria-required="true"|<input[^>]*aria-required="true"[^>]*' . preg_quote($needle, '/') . '/',
                $this->tpl,
                "$needle must have aria-required=\"true\""
            );
        }
    }
    public function testErrorContainerIsAlert(): void
    {
        $this->assertMatchesRegularExpression('/id="form-error"[^>]*role="alert"|role="alert"[^>]*id="form-error"/', $this->tpl);
        $this->assertStringContainsString('aria-live="assertive"', $this->tpl);
    }
    public function testSuccessRegionIsStatus(): void
    {
        $this->assertStringContainsString('role="status"', $this->tpl);
        $this->assertStringContainsString('aria-live="polite"', $this->tpl);
    }
    public function testRequiredMarkerLegendPresent(): void
    {
        $this->assertStringContainsString('class="req"', $this->tpl);
        $this->assertStringContainsString('povinné', $this->tpl);
    }
}
