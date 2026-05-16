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
        $this->assertStringContainsString('--c-text-soft: #725F56', $this->css, 'sub-text must be the shipped soft-brown token #725F56');
        $this->assertStringContainsString('--c-text: #62534C', $this->css, 'body text must be the shipped brown token #62534C');
        $this->assertStringNotContainsString('#7A7A7A', $this->css, 'old sub-AA #7A7A7A must be gone');
        $this->assertStringNotContainsString('#6A6A6A', $this->css, 'old soft-text #6A6A6A must be gone');
        $this->assertStringNotContainsString('#3D3D3D', $this->css, 'old body-text #3D3D3D must be gone');
    }
    /** Relative luminance per WCAG 2.x */
    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $ch = static function (int $v): float {
            $s = $v / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $ch(hexdec(substr($hex, 0, 2)))
             + 0.7152 * $ch(hexdec(substr($hex, 2, 2)))
             + 0.0722 * $ch(hexdec(substr($hex, 4, 2)));
    }
    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
    public function testTextTokensMeetAaOnCreamAndWhite(): void
    {
        $cream = '#FEF9F3';
        $white = '#FFFFFF';
        // body text must be AA (and is AAA here)
        $this->assertGreaterThanOrEqual(4.5, $this->contrast('#62534C', $cream), '--c-text on cream must be >= 4.5:1');
        $this->assertGreaterThanOrEqual(4.5, $this->contrast('#62534C', $white), '--c-text on white must be >= 4.5:1');
        // muted/sub text must be AA for normal-size text
        $this->assertGreaterThanOrEqual(4.5, $this->contrast('#725F56', $cream), '--c-text-soft on cream must be >= 4.5:1');
        $this->assertGreaterThanOrEqual(4.5, $this->contrast('#725F56', $white), '--c-text-soft on white must be >= 4.5:1');
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
