<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        Config::load(__DIR__ . '/../fixtures/config.test.php');
    }

    public function testGetTopLevel(): void
    {
        $this->assertSame('test', Config::get('app.env'));
    }

    public function testGetNested(): void
    {
        $this->assertSame('kuko_test', Config::get('db.name'));
    }

    public function testGetDefault(): void
    {
        $this->assertSame('fallback', Config::get('missing.key', 'fallback'));
    }

    public function testMissingWithoutDefaultThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        Config::get('totally.missing');
    }

    public function testIsLoaded(): void
    {
        $this->assertTrue(Config::isLoaded());
        Config::reset();
        $this->assertFalse(Config::isLoaded());
    }
}
