<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class GalleryHomepageWiringTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 3);
    }

    public function testHomepageRouteUsesHomepageSet(): void
    {
        $src = (string) file_get_contents($this->root . '/public/index.php');
        // The "/" homepage route must use homepageSet(), not listVisible() + array_slice cap.
        $this->assertStringContainsString('->homepageSet()', $src, 'homepage route must use homepageSet()');
        $this->assertStringNotContainsString('array_slice', $src, 'homepage cap via array_slice must be removed');
    }

    public function testGaleriaRouteStillUsesListVisible(): void
    {
        $src = (string) file_get_contents($this->root . '/public/index.php');
        // The /galeria route still renders ALL visible photos.
        $this->assertStringContainsString("'/galeria'", $src);
        $this->assertStringContainsString('->listVisible()', $src, '/galeria must still use listVisible()');
    }

    public function testMigrationFileExistsWithAddColumn(): void
    {
        $files = glob($this->root . '/private/migrations/*_gallery_homepage.sql') ?: [];
        $this->assertCount(1, $files, 'exactly one gallery_homepage migration must exist');
        $sql = (string) file_get_contents($files[0]);
        $this->assertMatchesRegularExpression(
            '/ALTER\s+TABLE\s+gallery_photos\s+ADD\s+COLUMN\s+on_homepage/i',
            $sql,
            'migration must ADD COLUMN on_homepage to gallery_photos'
        );
        $this->assertMatchesRegularExpression('#/006_gallery_homepage\.sql$#', $files[0], 'migration uses next free number 006');
    }

    public function testAdminGalleryRouteHasHomepageEndpoint(): void
    {
        $src = (string) file_get_contents($this->root . '/public/admin/index.php');
        $this->assertStringContainsString("/admin/gallery/{id}/homepage", $src, 'admin must expose the homepage toggle route');
        $this->assertStringContainsString('setHomepage', $src, 'admin route must call setHomepage');
    }
}
