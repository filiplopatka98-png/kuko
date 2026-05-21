<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class OnasCardsTest extends TestCase
{
    private string $t;
    protected function setUp(): void { $this->t = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/sections/o-nas.php'); }
    public function testAllFourIconsFromAssets(): void
    {
        foreach (['playground.svg','coffee.svg','friendship.svg','balloons.svg'] as $svg) {
            $this->assertStringContainsString('/assets/icons/' . $svg, $this->t, "missing icon $svg");
        }
    }
    public function testReservationButtonPresent(): void
    {
        $this->assertStringContainsString('/rezervacia', $this->t);
        $this->assertMatchesRegularExpression('/REZERVOVA|Rezervova/u', $this->t);
    }
    public function testNoH1Introduced(): void
    {
        $this->assertSame(0, substr_count($this->t, '<h1'), 'o-nas section must not contain an <h1');
    }
    public function testCssThickBorderAndStraddleButton(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/translate\(-?50%,\s*50%\)/', $css, 'straddling button rule missing');
    }
    public function testCardBodyIsDivNotParagraph(): void
    {
        // Card content is HTML from the admin editor (Quill), which wraps text
        // in <p>. A <p class="card__body"> outer wrapper would auto-close on
        // the inner <p>, leaving an empty .card__body and orphan paragraphs
        // with unexpected margins (visible as weird spacing in O nás).
        $this->assertStringNotContainsString('<p class="card__body"', $this->t);
        $this->assertSame(4, substr_count($this->t, '<div class="card__body">'),
            'expected 4 card__body div wrappers');
    }
}
