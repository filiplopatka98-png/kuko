<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class BrandAssetsTest extends TestCase
{
    private string $img;
    protected function setUp(): void { $this->img = \dirname(__DIR__, 3) . '/public/assets/img/'; }
    public function testLogoRegenerated(): void
    {
        foreach (['logo.png','logo.webp'] as $f) {
            $this->assertFileExists($this->img . $f);
            $info = getimagesize($this->img . $f);
            $this->assertNotFalse($info, "$f not a valid image");
            $this->assertLessThanOrEqual(640, $info[0], "$f width must be <=640");
            $this->assertGreaterThan(120, $info[0], "$f suspiciously small");
        }
    }
    public function testRainbowExists(): void
    {
        foreach (['rainbow.png','rainbow.webp'] as $f) {
            $this->assertFileExists($this->img . $f);
            $info = getimagesize($this->img . $f);
            $this->assertNotFalse($info, "$f not a valid image");
            $this->assertLessThanOrEqual(360, $info[0], "$f width must be <=360");
            // rainbow crop must be wider than tall (it's an arc, no text)
            $this->assertGreaterThan($info[1], $info[0], "$f should be landscape (rainbow arc)");
        }
    }
}
