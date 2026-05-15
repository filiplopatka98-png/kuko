<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class GalleryDirResolutionTest extends TestCase
{
    public function testGalleryDirUsesAssetDocRootNotHardcodedPublic(): void
    {
        $root = \dirname(__DIR__, 3);
        foreach (['/public/index.php', '/public/admin/index.php'] as $f) {
            $src = file_get_contents($root . $f);
            $this->assertStringContainsString("Asset::docRoot() . '/assets/img/gallery'", $src, "$f must resolve gallery dir via Asset::docRoot()");
            $this->assertStringNotContainsString("APP_ROOT . '/public/assets/img/gallery'", $src, "$f must not hardcode APP_ROOT/public path");
        }
    }
}
