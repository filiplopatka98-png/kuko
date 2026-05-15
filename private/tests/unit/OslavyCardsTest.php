<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class OslavyCardsTest extends TestCase
{
    private string $t;
    protected function setUp(): void { $this->t = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/sections/oslavy.php'); }
    public function testPackageIconsFromAssets(): void
    {
        foreach (['badge-balloon.svg','badge-balloons.svg','badge-crown.svg'] as $svg) {
            $this->assertStringContainsString('/assets/icons/' . $svg, $this->t, "missing $svg");
        }
    }
    public function testReservationButtonsPresent(): void
    {
        $this->assertStringContainsString('/rezervacia', $this->t);
        $this->assertMatchesRegularExpression('/Rezervova\x{0165} bal\x{00ED}\x{010D}ek/u', $this->t);
    }
    public function testNoH1(): void
    {
        $this->assertSame(0, substr_count($this->t, '<h1'), 'oslavy must not contain <h1');
    }
    public function testStraddleCss(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/translate\(-?50%,\s*-?50%\)/', $css, 'top badge straddle missing');
    }
}
