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
        $this->assertStringContainsString('#FBEEF5', $css);
    }
}
