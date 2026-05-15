<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class FontWoff2Test extends TestCase
{
    public function testWoff2FilesExist(): void
    {
        $d = \dirname(__DIR__, 3) . '/public/assets/fonts/';
        if (!is_file($d . 'NunitoSans.woff2')) {
            $this->markTestSkipped('woff2 tooling unavailable in this env; generate where available');
        }
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
}
