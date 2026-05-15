<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class AssetWiringTest extends TestCase
{
    public function testTemplatesUseAssetUrl(): void
    {
        $root = \dirname(__DIR__, 3);
        foreach ([
            '/private/templates/head.php',
            '/private/templates/layout.php',
            '/private/templates/admin/layout.php',
            '/private/templates/layout-minimal.php',
        ] as $f) {
            $this->assertStringContainsString('Asset::url(', file_get_contents($root . $f), "$f not cache-busted");
        }
    }
}
