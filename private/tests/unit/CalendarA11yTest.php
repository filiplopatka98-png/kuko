<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CalendarA11yTest extends TestCase
{
    private string $js;

    protected function setUp(): void
    {
        // __DIR__ = private/tests/unit  → depth 3 → repo root
        $this->js = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/js/rezervacia.js');
    }

    public function testGridRolesPresent(): void
    {
        $this->assertStringContainsString("'grid'", $this->js);
        $this->assertStringContainsString("'gridcell'", $this->js);
        $this->assertStringContainsString('aria-selected', $this->js);
        $this->assertStringContainsString('aria-disabled', $this->js);
        $this->assertStringContainsString('aria-label', $this->js);
    }

    public function testKeyboardHandlersPresent(): void
    {
        foreach (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End','PageUp','PageDown'] as $k) {
            $this->assertStringContainsString($k, $this->js, "missing key handler: $k");
        }
    }

    public function testLiveRegionPresent(): void
    {
        $this->assertStringContainsString('aria-live', $this->js);
    }
}
