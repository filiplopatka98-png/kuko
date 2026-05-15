<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use Kuko\Asset;
use PHPUnit\Framework\TestCase;

final class AssetTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kuko-asset-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/assets/css', 0777, true);
        file_put_contents($this->root . '/assets/css/x.css', 'body{}');
        touch($this->root . '/assets/css/x.css', 1700000000);
    }
    protected function tearDown(): void
    {
        @unlink($this->root . '/assets/css/x.css');
        @unlink($this->root . '/assets/css/x.min.css');
        @unlink($this->root . '/assets/css/y.min.css');
        @rmdir($this->root . '/assets/css'); @rmdir($this->root . '/assets'); @rmdir($this->root);
    }
    public function testStampAppendsFilemtimeWhenFileExists(): void
    {
        $this->assertSame('/assets/css/x.css?v=1700000000', Asset::stamp('/assets/css/x.css', $this->root));
    }
    public function testStampReturnsPathUnchangedWhenMissing(): void
    {
        $this->assertSame('/assets/css/missing.css', Asset::stamp('/assets/css/missing.css', $this->root));
    }
    public function testStampPreservesExistingQueryByAppendingWithAmp(): void
    {
        $this->assertSame('/assets/css/x.css?a=1&v=1700000000', Asset::stamp('/assets/css/x.css?a=1', $this->root));
    }
    public function testPrefersMinifiedSiblingWhenItExists(): void
    {
        file_put_contents($this->root . '/assets/css/x.min.css', 'body{}');
        touch($this->root . '/assets/css/x.min.css', 1700000500);
        // x.css also exists from setUp (mtime 1700000000)
        $this->assertSame('/assets/css/x.min.css?v=1700000500', Asset::stamp('/assets/css/x.css', $this->root));
    }
    public function testDoesNotDoubleMinify(): void
    {
        file_put_contents($this->root . '/assets/css/y.min.css', 'a{}');
        touch($this->root . '/assets/css/y.min.css', 1700000600);
        $this->assertSame('/assets/css/y.min.css?v=1700000600', Asset::stamp('/assets/css/y.min.css', $this->root));
    }
    public function testFallsBackToOriginalWhenNoMinSibling(): void
    {
        // x.css exists (setUp), no x.min.css for THIS assertion -> remove if present
        @unlink($this->root . '/assets/css/x.min.css');
        $this->assertSame('/assets/css/x.css?v=1700000000', Asset::stamp('/assets/css/x.css', $this->root));
    }
    public function testDocRootPrefersServerDocumentRootWhenValidDir(): void
    {
        $orig = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $this->root;          // a real temp dir from setUp
        try {
            $this->assertSame($this->root, \Kuko\Asset::docRoot());
        } finally {
            if ($orig === null) unset($_SERVER['DOCUMENT_ROOT']); else $_SERVER['DOCUMENT_ROOT'] = $orig;
        }
    }
    public function testDocRootFallsBackToAppRootPublicWhenNoServerDocRoot(): void
    {
        $orig = $_SERVER['DOCUMENT_ROOT'] ?? null;
        unset($_SERVER['DOCUMENT_ROOT']);
        try {
            // APP_ROOT is defined by the test bootstrap to the repo root which HAS /public
            $this->assertSame(APP_ROOT . '/public', \Kuko\Asset::docRoot());
        } finally {
            if ($orig !== null) $_SERVER['DOCUMENT_ROOT'] = $orig;
        }
    }
    public function testCommittedMinFilesNotOlderThanSource(): void
    {
        $pub = \dirname(__DIR__, 3) . '/public';
        $pairs = [
            '/assets/css/main.css', '/assets/css/rezervacia.css', '/assets/css/admin.css',
            '/assets/js/main.js', '/assets/js/rezervacia.js',
        ];
        foreach ($pairs as $src) {
            $min = preg_replace('/\.(css|js)$/', '.min.$1', $src);
            $this->assertFileExists($pub . $min, "missing committed minified $min — run private/scripts/build-assets.php");
            $this->assertGreaterThanOrEqual(
                filemtime($pub . $src),
                filemtime($pub . $min),
                "$min is older than its source — re-run private/scripts/build-assets.php and commit"
            );
        }
    }
}
