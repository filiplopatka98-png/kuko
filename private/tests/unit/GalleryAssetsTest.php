<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression: seed-cms.php seeds gallery_photos rows with filename
 * "galeria_N.jpg" / webp "galeria_N.webp", and sections/galeria.php renders
 * them under /assets/img/gallery/. The starter files must therefore exist in
 * public/assets/img/gallery/ or the homepage gallery shows broken images.
 */
final class GalleryAssetsTest extends TestCase
{
    public function testSeededStarterGalleryFilesExistInGalleryDir(): void
    {
        $dir = \dirname(__DIR__, 3) . '/public/assets/img/gallery';
        for ($i = 1; $i <= 5; $i++) {
            $this->assertFileExists($dir . "/galeria_{$i}.jpg", "missing starter gallery jpg #$i");
            $this->assertFileExists($dir . "/galeria_{$i}.webp", "missing starter gallery webp #$i");
        }
    }
}
