<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class FontWoff2Test extends TestCase
{
    public function testWoff2FilesExist(): void
    {
        $d = \dirname(__DIR__, 3) . '/public/assets/fonts/';
        $this->assertFileExists($d . 'NunitoSans.woff2');
        $this->assertFileExists($d . 'NunitoSans-Italic.woff2');
        $this->assertGreaterThan(1000, filesize($d . 'NunitoSans.woff2'));
    }
    public function testFontFacePrefersWoff2(): void
    {
        $root = \dirname(__DIR__, 3);
        foreach (['/public/assets/css/main.css', '/public/assets/css/admin.css', '/private/templates/layout-minimal.php'] as $f) {
            $css = file_get_contents($root . $f);
            if (strpos($css, '@font-face') === false) continue;
            $this->assertStringContainsString('woff2', $css, "$f @font-face must reference woff2");
            // woff2 must come before truetype in the src list
            $this->assertLessThan(strpos($css, 'truetype'), strpos($css, 'woff2'), "$f must list woff2 before ttf");
        }
    }
    public function testFontPreloadPointsToWoff2(): void
    {
        $root = \dirname(__DIR__, 3);
        foreach (['/private/templates/head.php', '/private/templates/layout-minimal.php'] as $f) {
            $html = file_get_contents($root . $f);
            $this->assertNotFalse($html, "$f must be readable");
            // Extract every <link rel="preload" ... > tag
            preg_match_all('/<link\b[^>]*rel="preload"[^>]*>/i', $html, $m);
            $preloads = $m[0];
            $this->assertNotEmpty($preloads, "$f must contain a preload link");
            $fontPreloads = array_values(array_filter($preloads, static fn (string $l): bool => stripos($l, 'as="font"') !== false));
            $this->assertNotEmpty($fontPreloads, "$f must preload a font");
            foreach ($fontPreloads as $link) {
                $this->assertStringContainsString('NunitoSans.woff2', $link, "$f font preload must point at woff2");
                $this->assertStringContainsString('type="font/woff2"', $link, "$f font preload must declare type font/woff2");
                $this->assertStringNotContainsString('href="/assets/fonts/NunitoSans.ttf"', $link, "$f must not preload the ttf");
            }
        }
    }
}
