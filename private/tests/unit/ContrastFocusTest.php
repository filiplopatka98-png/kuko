<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ContrastFocusTest extends TestCase
{
    private string $css;
    protected function setUp(): void { $this->css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css'); }

    public function testSubTextColorIsAaCompliant(): void
    {
        $this->assertStringContainsString('--c-text-soft: #6A6A6A', $this->css, 'sub-text must be AA token #6A6A6A');
        $this->assertStringNotContainsString('#7A7A7A', $this->css, 'old sub-AA #7A7A7A must be gone');
    }
    public function testSiteWideFocusVisibleExists(): void
    {
        // a :focus-visible rule that is NOT the calendar-gridcell one
        $this->assertMatchesRegularExpression('/:focus-visible\s*\{[^}]*outline/s', $this->css);
        // must reference generic interactive elements (a/button/input) — not only .calendar__grid
        $this->assertMatchesRegularExpression('/(a|button|input)[^{]*:focus-visible|:where\([^)]*(a|button|input)[^)]*\)\s*:focus-visible/s', $this->css);
    }
    public function testReducedMotionBlockPresent(): void
    {
        $this->assertStringContainsString('prefers-reduced-motion', $this->css);
    }
}
