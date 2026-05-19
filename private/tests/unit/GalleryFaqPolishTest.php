<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class GalleryFaqPolishTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = \dirname(__DIR__, 3); }

    public function testGalleryPageHasNoRainbow(): void
    {
        $g = file_get_contents($this->root . '/private/templates/pages/gallery.php');
        $this->assertStringNotContainsString('section__rainbow', $g, 'standalone /galeria must not show the rainbow');
        // homepage section keeps it
        $s = file_get_contents($this->root . '/private/templates/sections/galeria.php');
        $this->assertStringContainsString('section__rainbow', $s, 'homepage galeria keeps the rainbow');
    }

    public function testLayoutEmitsPageClass(): void
    {
        $l = file_get_contents($this->root . '/private/templates/layout.php');
        $this->assertMatchesRegularExpression('/<body class="page-<\?=\s*e\(\$pageType/', $l);
    }

    public function testFooterFlushOnGalleryAndFaq(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression(
            '/\.page-gallery \.footer,\s*\.page-faq \.footer \{[^}]*margin-top: 0/',
            $css
        );
    }

    public function testCtaPanelStandsOut(): void
    {
        // White card + visible border + shadow so it never blends into the
        // section background (was pink-soft, blended on the galeria bg).
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression(
            '/\.cta-panel \{[^}]*background: var\(--c-white\)[^}]*border: 2px solid var\(--c-purple\)/',
            $css
        );
    }
}
