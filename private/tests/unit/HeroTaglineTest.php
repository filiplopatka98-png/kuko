<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class HeroTaglineTest extends TestCase
{
    public function testHeroHasTaglineContentBlock(): void
    {
        $h = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/sections/hero.php');
        $this->assertStringContainsString("hero.tagline", $h);
        $this->assertStringContainsString('class="hero__tagline"', $h);
        $this->assertSame(1, substr_count($h, '<h1'), 'hero must keep exactly one <h1');
    }
    public function testSeedListsHeroTagline(): void
    {
        $s = file_get_contents(\dirname(__DIR__, 3) . '/private/scripts/seed-cms.php');
        $this->assertStringContainsString('hero.tagline', $s);
    }
    public function testTaglineStyled(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        $this->assertStringContainsString('.hero__tagline', $css);
    }
}
