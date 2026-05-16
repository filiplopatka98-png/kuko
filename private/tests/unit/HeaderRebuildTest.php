<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class HeaderRebuildTest extends TestCase
{
    private string $nav;
    protected function setUp(): void { $this->nav = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/nav.php'); }
    public function testTopbarHasContactIconsAndSocial(): void
    {
        $this->assertStringContainsString('mailto:info@kuko-detskysvet.sk', $this->nav);
        $this->assertStringContainsString('tel:+421915319934', $this->nav);
        $this->assertMatchesRegularExpression('/Sledujte n\x{00E1}s/u', $this->nav); // "Sledujte nás"
        $this->assertStringContainsString('Social::url', $this->nav);
        $this->assertMatchesRegularExpression('/topbar[^>]*>.*(svg|\.svg)/s', $this->nav); // an icon in topbar
    }
    public function testLogoCenteredAndHamburgerPreserved(): void
    {
        $this->assertStringContainsString('class="nav__brand"', $this->nav);
        $this->assertStringContainsString('Asset::url(\'/assets/img/logo.png\')', $this->nav);
        $this->assertStringContainsString('id="primary-nav"', $this->nav);
        $this->assertStringContainsString('class="nav__toggle"', $this->nav);
    }
    public function testNavLinksIntact(): void
    {
        foreach (['/#domov','/#o-nas','/#oslavy','/#cennik','/#galeria','/#kontakt'] as $href) {
            $this->assertStringContainsString('href="' . $href . '"', $this->nav);
        }
    }
    public function testPinkNavBandCss(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        $this->assertStringContainsString('#FDF7FF', $css);
    }
    public function testTopbarHasNoBorderBottom(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/^\.topbar\s*\{/m', $css, 'expected a .topbar rule');
        if (preg_match('/^\.topbar\s*\{([^}]*)\}/m', $css, $m)) {
            $this->assertStringNotContainsString('border-bottom', $m[1], '.topbar must not have a border-bottom');
        } else {
            $this->fail('Could not isolate the .topbar { } rule');
        }
    }
    public function testTopbarIconSizingConsistentAndNotShrunkOnMobile(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        // .topbar__icon and the social img share the same icon dimension (18px)
        $this->assertMatchesRegularExpression('/\.topbar__icon\s*\{[^}]*width:\s*18px[^}]*\}/', $css);
        $this->assertMatchesRegularExpression('/\.topbar__social-link\s+img\s*\{[^}]*width:\s*18px[^}]*\}/', $css);
        // The mobile media block must NOT set a smaller .topbar__icon / social img size.
        if (preg_match('/@media\s*\(max-width:\s*768px\)\s*\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}/', $css, $mm)) {
            $block = $mm[1];
            $this->assertStringNotContainsString('.topbar__icon', $block, 'mobile block must not resize .topbar__icon');
            $this->assertDoesNotMatchRegularExpression('/\.topbar__social-link\s+img/', $block, 'mobile block must not resize social img');
        }
    }
    public function testLogoEnlargedAndOverlapsTopbar(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        // Desktop logo height >= 140px
        if (preg_match('/\.nav__brand\s+img\s*\{[^}]*height:\s*(\d+)px[^}]*\}/', $css, $hm)) {
            $this->assertGreaterThanOrEqual(140, (int) $hm[1], '.nav__brand img desktop height must be >= 140px');
        } else {
            $this->fail('Could not find .nav__brand img height');
        }
        // Negative offset to overlap the topbar (margin-top negative OR translateY negative)
        $hasNegOffset = (bool) preg_match('/\.nav__brand(-row)?\s*\{[^}]*(margin-top:\s*-|transform:\s*translateY\(\s*-)/', $css);
        $this->assertTrue($hasNegOffset, '.nav__brand/.nav__brand-row must have a negative offset to overlap the topbar');
    }
}
