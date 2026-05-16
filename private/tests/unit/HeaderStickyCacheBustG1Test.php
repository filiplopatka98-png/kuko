<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class HeaderStickyCacheBustG1Test extends TestCase
{
    private function root(): string { return \dirname(__DIR__, 3); }
    private function css(): string { return file_get_contents($this->root() . '/public/assets/css/main.css'); }
    private function mainJs(): string { return file_get_contents($this->root() . '/public/assets/js/main.js'); }

    public function testExactLogoMargins(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.nav__brand\s*\{[^}]*margin-top:\s*-56px[^}]*margin-bottom:\s*-16px/',
            $this->css(),
            '.nav__brand must use the exact requested margins (-56px / -16px)'
        );
    }

    public function testExactRainbowCss(): void
    {
        $css = $this->css();
        foreach ([
            'width:\s*350px',
            'margin-top:\s*-132px',
            'margin-bottom:\s*-85px',
            'margin-left:\s*calc\(50% - 225px\)',
            'transform:\s*rotate\(-15deg\)',
        ] as $needle) {
            $this->assertMatchesRegularExpression(
                '/\.section__rainbow\s*\{[^}]*' . $needle . '/',
                $css,
                ".section__rainbow must declare $needle"
            );
        }
    }

    public function testStickyCollapseWiredUp(): void
    {
        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*769px\)\s*\{\s*\.nav\.is-stuck\s+\.nav__brand-row\s*\{[^}]*display:\s*none/',
            $this->css(),
            'CSS must hide .nav__brand-row when .nav.is-stuck on desktop'
        );
        $js = $this->mainJs();
        $this->assertStringContainsString("classList.toggle('is-stuck'", $js, 'main.js must toggle is-stuck');
        $this->assertStringContainsString("addEventListener('scroll'", $js, 'main.js must listen to scroll');
    }

    public function testGalleryImportIsCacheBusted(): void
    {
        $js = $this->mainJs();
        // The dynamic import must use the layout-injected versioned URL, not a
        // bare './gallery.js' (which carries no ?v= and is served stale).
        $this->assertStringContainsString('window.__kukoAssets', $js, 'main.js must read injected asset URLs');
        $this->assertMatchesRegularExpression(
            '/import\(\s*A\.gallery\s*\|\|\s*[\'"]\.\/gallery\.js[\'"]\s*\)/',
            $js,
            'gallery.js import must prefer the versioned URL'
        );
        $this->assertMatchesRegularExpression(
            '/import\(\s*A\.map\s*\|\|\s*[\'"]\.\/map\.js[\'"]\s*\)/',
            $js,
            'map.js import must prefer the versioned URL'
        );
        $layout = file_get_contents($this->root() . '/private/templates/layout.php');
        $this->assertStringContainsString('window.__kukoAssets', $layout, 'layout must inject __kukoAssets');
        $this->assertMatchesRegularExpression(
            "/Asset::url\('\/assets\/js\/gallery\.js'\)/",
            $layout,
            'layout must emit the Asset::url-stamped gallery.js URL'
        );
        $this->assertMatchesRegularExpression(
            "/Asset::url\('\/assets\/js\/map\.js'\)/",
            $layout,
            'layout must emit the Asset::url-stamped map.js URL'
        );
    }
}
