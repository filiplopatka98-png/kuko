<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class MobileHeaderTest extends TestCase
{
    private string $nav;
    private string $css;

    protected function setUp(): void
    {
        $root = \dirname(__DIR__, 3);
        $this->nav = file_get_contents($root . '/private/templates/nav.php');
        $this->css = file_get_contents($root . '/public/assets/css/main.css');
    }

    public function testContactBlockInsideHamburgerPanel(): void
    {
        // Contact + social duplicated inside #primary-nav for the mobile menu.
        $navPos = strpos($this->nav, 'id="primary-nav"');
        $contactPos = strpos($this->nav, 'class="nav__contact"');
        $this->assertNotFalse($contactPos, '.nav__contact block must exist');
        $this->assertGreaterThan($navPos, $contactPos, '.nav__contact must be inside #primary-nav');
        $this->assertStringContainsString('mailto:info@kukodetskysvet.sk', $this->nav);
        $this->assertStringContainsString('tel:+421915319934', $this->nav);
        $this->assertStringContainsString('class="nav__socials"', $this->nav);
        // Hidden on desktop (topbar covers it there).
        $this->assertMatchesRegularExpression('/\.nav__contact\s*\{\s*display:\s*none/', $this->css);
    }

    public function testMobileMediaRules(): void
    {
        // Extract the max-width:768px block that contains the topbar hide.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\) \{[^@]*\.topbar \{ display: none; \}/s',
            $this->css,
            'topbar must be hidden on mobile'
        );
        // Mobile: the big logo header is hidden; the sticky pink bar becomes
        // the header with a compact logo (left) + hamburger (right).
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\)[\s\S]*\.nav \{ display: none; \}/',
            $this->css,
            'big logo header hidden on mobile'
        );
        $this->assertMatchesRegularExpression(
            '/\.nav__bar \{ justify-content: space-between/',
            $this->css,
            'sticky bar: compact logo left, hamburger right'
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\)[\s\S]*\.nav__brand--bar img \{ height: 48px/',
            $this->css,
            'compact logo shown in the sticky bar on mobile'
        );
        // Pink round hamburger + white bars + X morph on open.
        $this->assertMatchesRegularExpression(
            '/\.nav__toggle \{[^}]*background: var\(--c-accent\)[^}]*border-radius: 50%/',
            $this->css
        );
        $this->assertStringContainsString('.nav__toggle[aria-expanded="true"] span:nth-child(1)', $this->css);
        $this->assertStringContainsString('.nav__toggle[aria-expanded="true"] span:nth-child(2)', $this->css);
        $this->assertStringContainsString('.nav__toggle[aria-expanded="true"] span:nth-child(3)', $this->css);
        // Contact links must NOT inherit the uppercase menu styling.
        $this->assertMatchesRegularExpression(
            '/\.nav__contact \.nav__contact-link \{[^}]*text-transform: none/',
            $this->css
        );
    }

    public function testSkipLinkFullyHiddenUntilFocus(): void
    {
        // top:-44px left a 1px sliver visible; must be fully off-screen.
        $this->assertMatchesRegularExpression('/\.skip-link\{[^}]*top:-60px/', $this->css);
        $this->assertStringContainsString('.skip-link:focus{top:8px}', $this->css);
    }
}
