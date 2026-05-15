<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class GalleryRedesignTest extends TestCase
{
    public function testSectionHasRainbowAndCtaAndRadius(): void
    {
        $root = \dirname(__DIR__, 3);
        $sec = file_get_contents($root . '/private/templates/sections/galeria.php');
        $this->assertStringContainsString('rainbow', $sec);
        $this->assertStringContainsString('/galeria', $sec);
        $css = file_get_contents($root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/border-radius:\s*30px/', $css);
    }
    public function testGalleryPageTemplateExists(): void
    {
        $f = \dirname(__DIR__, 3) . '/private/templates/pages/gallery.php';
        $this->assertFileExists($f);
        $src = file_get_contents($f);
        $this->assertSame(1, substr_count($src, '<h1'), 'gallery page must have exactly one <h1');
        $this->assertStringContainsString('data-lightbox', $src);
    }
    public function testRouteRegistered(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/index.php');
        $this->assertMatchesRegularExpression("#['\"]/galeria['\"]#", $idx, '/galeria route not registered');
    }
    public function testSeedHasSixthPhoto(): void
    {
        $s = file_get_contents(\dirname(__DIR__, 3) . '/private/scripts/seed-cms.php');
        // 6 gallery photo rows referenced (galeria_1..5 + a 6th reuse)
        $this->assertGreaterThanOrEqual(6, preg_match_all('/galeria_\d/', $s));
    }
}
