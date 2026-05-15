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
}
