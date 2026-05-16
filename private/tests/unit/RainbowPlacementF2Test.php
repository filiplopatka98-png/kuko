<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class RainbowPlacementF2Test extends TestCase
{
    private function css(): string
    {
        return file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
    }

    public function testSharedRuleIsBiggerAndTilted(): void
    {
        $css = $this->css();
        $this->assertMatchesRegularExpression(
            '/\.section__rainbow\s*\{[^}]*transform:\s*rotate\(-?\d+deg\)/',
            $css,
            'shared .section__rainbow must keep a rotate() tilt'
        );
        $this->assertMatchesRegularExpression(
            '/\.section__rainbow\s*\{[^}]*width:\s*(\d+)px/',
            $css,
            'shared .section__rainbow must declare a px width'
        );
        \preg_match('/\.section__rainbow\s*\{[^}]*width:\s*(\d+)px/', $css, $m);
        $this->assertGreaterThan(260, (int) $m[1], 'rainbow must be bigger than the old 260px');
    }

    public function testStraddleIsHomepageScoped(): void
    {
        $this->assertMatchesRegularExpression(
            '/#galeria\s+\.section__rainbow\s*\{[^}]*margin-top:\s*-[\d.]+rem/',
            $this->css(),
            '#galeria .section__rainbow must pull up with a negative margin-top'
        );
    }
}
