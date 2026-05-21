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
    public function testPerFieldFallbackNotAllOrNothingGate(): void
    {
        // Every field must be admin-editable independently — an empty DB
        // value falls back to the per-field default, NOT to a verbatim
        // hardcoded block that would ignore other admin edits. Guard against
        // the regression of reintroducing an all-or-nothing $hasExtended
        // gate.
        $this->assertStringNotContainsString('$hasExtended', $this->t);
        $this->assertStringContainsString('$pick(', $this->t, 'per-field pick() fallback must be used');
        $this->assertStringContainsString("'mini'", $this->t);
        $this->assertStringContainsString("'maxi'", $this->t);
        $this->assertStringContainsString("'closed'", $this->t);
    }
    public function testPackageDescIsDivNotParagraph(): void
    {
        // Description HTML comes from the admin editor (Quill) which wraps
        // text in <p>. A <p class="package__desc"> outer wrapper would
        // auto-close on the inner <p> — same regression as O nás cards.
        $this->assertStringNotContainsString('<p class="package__desc"', $this->t);
        $this->assertStringContainsString('<div class="package__desc">', $this->t);
    }
}
