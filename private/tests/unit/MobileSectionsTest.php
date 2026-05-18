<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class MobileSectionsTest extends TestCase
{
    private string $css;
    private string $kontakt;

    protected function setUp(): void
    {
        $root = \dirname(__DIR__, 3);
        $this->css = file_get_contents($root . '/public/assets/css/main.css');
        $this->kontakt = file_get_contents($root . '/private/templates/sections/kontakt.php');
    }

    public function testMobileMenuOverlaysContent(): void
    {
        // Open menu must be absolutely positioned so it floats over the page
        // instead of pushing content down.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\)[\s\S]*\.nav__menu \{[^}]*position: absolute/',
            $this->css
        );
    }

    public function testSocialIconsAreRoundInHamburger(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.nav__contact \.topbar__social-link \{[^}]*border-radius: 50%[^}]*\}/',
            $this->css
        );
        $this->assertMatchesRegularExpression('/\.nav__contact \.topbar__social-link \{[^}]*flex: none/', $this->css);
    }

    public function testGalleryTwoColumnsOnMobile(): void
    {
        // The forced single-column <=480px rule was removed; 2 cols stays.
        $this->assertStringNotContainsString(
            '@media (max-width: 480px) { .gallery { grid-template-columns: 1fr; } }',
            $this->css
        );
        $this->assertStringContainsString(
            '@media (max-width: 768px) { .gallery { grid-template-columns: repeat(2, 1fr); } }',
            $this->css
        );
    }

    public function testPackagesExtraGapOnMobile(): void
    {
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 1024px\) \{[\s\S]*\.packages-grid \{[^}]*gap: var\(--s-10\)/',
            $this->css,
            'single-column packages need a large gap so straddle CTAs do not collide'
        );
    }

    public function testCennikPhotoGluedToPanelOnMobile(): void
    {
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\) \{[\s\S]*\.cennik__inner \{ grid-template-columns: 1fr; gap: 0;/',
            $this->css
        );
        $this->assertMatchesRegularExpression('/\.cennik__photo \{[^}]*border-bottom-left-radius: 0/', $this->css);
    }

    public function testKontaktSocialIsShortLabelOneRow(): void
    {
        $this->assertStringContainsString('Sledujte nás:', $this->kontakt);
        $this->assertStringNotContainsString('Sledujte nás na sociálnych sieťach', $this->kontakt);
        $this->assertMatchesRegularExpression(
            '/\.contact-card--social \{[^}]*flex-wrap: nowrap/',
            $this->css
        );
    }

    public function testFooterNavTighterGapsOnMobile(): void
    {
        $this->assertStringContainsString(
            '@media (max-width: 768px) { .footer__nav { gap: 0.4rem 1rem; } }',
            $this->css
        );
    }
}
