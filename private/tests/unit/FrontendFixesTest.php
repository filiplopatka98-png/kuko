<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class FrontendFixesTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = \dirname(__DIR__, 3); }
    public function testBalloonsIconNotWhite(): void
    {
        $svg = file_get_contents($this->root . '/public/assets/icons/balloons.svg');
        $this->assertStringNotContainsString('fill="#fff"', $svg);
        $this->assertStringContainsString('#c9a8e1', $svg);
    }
    public function testRainbowTransparent(): void
    {
        $f = $this->root . '/public/assets/img/rainbow.png';
        $this->assertFileExists($f);
        $im = imagecreatefrompng($f);
        $this->assertTrue(imageistruecolor($im));
        // top-left corner must be transparent (alpha 127), not white
        $a = (imagecolorat($im, 1, 1) >> 24) & 0x7F;
        $this->assertSame(127, $a, 'rainbow.png corner must be fully transparent');
    }
    public function testRainbowTilted(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/\.section__rainbow\s*\{[^}]*rotate\(-?\d+deg\)/s', $css);
    }
    public function testTopbarPhoneInlineSvgAndSocialVisible(): void
    {
        $nav = file_get_contents($this->root . '/private/templates/nav.php');
        // phone link no longer uses the white contact-us.svg img; uses an inline svg
        $this->assertDoesNotMatchRegularExpression('/tel:\+421915319934"[^>]*>\s*<img[^>]*contact-us\.svg/s', $nav);
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/\.topbar__social-link\s*\{[^}]*border-radius:\s*50%/s', $css);
    }
    public function testMapStackingContained(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/\.kontakt__map-wrap\s*\{[^}]*isolation:\s*isolate/s', $css);
        $this->assertMatchesRegularExpression('/\.nav\s*\{[^}]*z-index:\s*200/s', $css);
    }
}
