<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FaviconAssetsTest extends TestCase
{
    private string $pub;
    protected function setUp(): void { $this->pub = \dirname(__DIR__, 3) . '/public'; }

    public function testIconFilesExistWithCorrectDimensions(): void
    {
        $expect = ['/favicon-16.png'=>16,'/favicon-32.png'=>32,'/apple-touch-icon.png'=>180,'/icon-192.png'=>192,'/icon-512.png'=>512];
        foreach ($expect as $rel => $size) {
            $f = $this->pub . $rel;
            $this->assertFileExists($f, "$rel missing");
            [$w,$h] = getimagesize($f);
            $this->assertSame($size, $w, "$rel width");
            $this->assertSame($size, $h, "$rel height");
        }
        $this->assertFileExists($this->pub . '/favicon.ico');
    }

    public function testManifestIsValidJson(): void
    {
        $m = json_decode((string) file_get_contents($this->pub . '/manifest.webmanifest'), true);
        $this->assertIsArray($m);
        $this->assertSame('KUKO detský svet', $m['name']);
        $this->assertNotEmpty($m['icons']);
    }

    public function testHeadReferencesIcons(): void
    {
        // Icons live in the shared _head-social.php partial, included by both
        // head.php (full layout) and layout-minimal.php (reservation page).
        $tpl = \dirname(__DIR__, 3) . '/private/templates';
        $h = file_get_contents($tpl . '/head.php');
        $this->assertStringContainsString('_head-social.php', $h, 'head.php must include the shared social/icons partial');
        $social = file_get_contents($tpl . '/_head-social.php');
        $this->assertStringContainsString('apple-touch-icon', $social);
        $this->assertStringContainsString('manifest.webmanifest', $social);
        $this->assertStringContainsString('rel="icon"', $social);
    }
}
