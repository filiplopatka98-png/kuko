<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class LightboxPerfR4Test extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function testGalleryJsResolvesWebpAndPreloadsNeighbors(): void
    {
        $js = file_get_contents($this->root() . '/public/assets/js/gallery.js');
        $this->assertStringContainsString('dataset.lightboxWebp', $js, 'gallery.js must read the webp dataset attribute');
        $this->assertMatchesRegularExpression('/new\s+Image\s*\(\s*\)/', $js, 'gallery.js must construct new Image() for neighbor preloading');
    }

    public function testGalleryJsKeepsKeyboardHandlers(): void
    {
        $js = file_get_contents($this->root() . '/public/assets/js/gallery.js');
        $this->assertStringContainsString("'Escape'", $js);
        $this->assertStringContainsString("'ArrowLeft'", $js);
        $this->assertStringContainsString("'ArrowRight'", $js);
    }

    public function testBothTemplatesExposeWebpLightboxAttribute(): void
    {
        $sec = file_get_contents($this->root() . '/private/templates/sections/galeria.php');
        $pag = file_get_contents($this->root() . '/private/templates/pages/gallery.php');
        $this->assertStringContainsString('data-lightbox-webp', $sec, 'galeria.php section must add data-lightbox-webp');
        $this->assertStringContainsString('data-lightbox-webp', $pag, 'gallery.php page must add data-lightbox-webp');
        // jpg fallback must remain intact for graceful degradation
        $this->assertStringContainsString('data-lightbox="', $sec);
        $this->assertStringContainsString('data-lightbox="', $pag);
    }

    public function testRainbowTiltAndTighterMargin(): void
    {
        // Rainbow redesign (F2): bigger, still tilted, closer to the heading,
        // and the strong upward straddle is homepage-scoped under #galeria.
        $css = file_get_contents($this->root() . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression(
            '/\.section__rainbow\s*\{[^}]*transform:\s*rotate\(-?\d+deg\)/',
            $css,
            '.section__rainbow must keep a rotate() tilt'
        );
        $this->assertMatchesRegularExpression(
            '/#galeria\s+\.section__rainbow\s*\{[^}]*margin-top:\s*-[\d.]+rem/',
            $css,
            '#galeria .section__rainbow must have a negative margin-top (homepage straddle)'
        );
    }
}
