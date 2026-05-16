<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class KontaktSectionTest extends TestCase
{
    private string $t;
    protected function setUp(): void { $this->t = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/sections/kontakt.php'); }
    public function testIconsFromAssetsAndSocial(): void
    {
        $this->assertStringContainsString('/assets/icons/home.svg', $this->t);
        $this->assertStringContainsString('/assets/icons/clock-1.svg', $this->t);
        $this->assertStringContainsString('/assets/icons/facebook-app-symbol.svg', $this->t);
        $this->assertStringContainsString('/assets/icons/instagram.svg', $this->t);
        $this->assertStringContainsString('Social::url', $this->t);
    }
    public function testSocialLabelPresent(): void
    {
        $this->assertMatchesRegularExpression('/Sledujte n\x{00E1}s/u', $this->t);
    }
    public function testNoH1(): void
    {
        $this->assertSame(0, substr_count($this->t, '<h1'), 'kontakt must not contain <h1');
    }
    public function testCssThickBorders(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        // a contact/social flex-row rule exists
        $this->assertMatchesRegularExpression('/kontakt|contact|social/i', $css);
    }
    public function testContactValueLinksMatchPlainText(): void
    {
        // phone/email links must look identical to the address/hours <strong>
        // text (same colour + bold), not the global pink underlined link.
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression(
            '/\.contact-card__value a\s*\{[^}]*color:\s*var\(--c-text\)[^}]*font-weight:\s*700/s',
            $css,
            'contact-card__value links must use --c-text colour and bold to match address/hours'
        );
    }
}
