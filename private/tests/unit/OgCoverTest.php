<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OgCoverTest extends TestCase
{
    public function testOgCoverExistsAt1200x630(): void
    {
        $f = \dirname(__DIR__, 3) . '/public/assets/img/og-cover.jpg';
        $this->assertFileExists($f);
        [$w,$h] = getimagesize($f);
        $this->assertSame(1200, $w);
        $this->assertSame(630, $h);
    }

    public function testHeadDefaultsOgImageToOgCover(): void
    {
        $h = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/head.php');
        $this->assertStringContainsString('og-cover.jpg', $h);
    }
}
