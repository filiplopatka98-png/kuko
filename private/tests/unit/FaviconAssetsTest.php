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
        $h = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/head.php');
        $this->assertStringContainsString('apple-touch-icon', $h);
        $this->assertStringContainsString('manifest.webmanifest', $h);
        $this->assertStringContainsString('rel="icon"', $h);
    }
}
