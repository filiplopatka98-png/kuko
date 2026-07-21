<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class AdminWpLayoutTest extends TestCase
{
    private string $l;
    protected function setUp(): void { $this->l = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/admin/layout.php'); }
    public function testSidebarFourTopLevelItems(): void
    {
        $this->assertStringContainsString('admin-sidebar', $this->l);
        // Sidebar is FOUR single top-level items, each .admin-nav-item--top.
        $expected = [
            '/admin'         => 'Rezerv\x{00E1}cie',
            '/admin/pages'   => 'Str\x{00E1}nky',
            '/admin/gallery' => 'Gal\x{00E9}ria',
            '/admin/contact' => 'Web &amp; syst\x{00E9}m',
        ];
        foreach ($expected as $href => $label) {
            $this->assertMatchesRegularExpression(
                '/href="' . preg_quote($href, '/') . '"\s+class="admin-nav-item admin-nav-item--top[^"]*">' . $label . '/u',
                $this->l,
                $href . ' must be a single top-level sidebar item labelled ' . $label
            );
        }
    }
    public function testOldGroupStructureGone(): void
    {
        // The STRÁNKY / NASTAVENIA group sub-lists are removed; tabs live in
        // the content area now.
        $this->assertStringNotContainsString('admin-nav-label', $this->l, 'group labels must be gone');
        $this->assertStringNotContainsString('admin-nav-group', $this->l, 'nav groups must be gone');
        $this->assertDoesNotMatchRegularExpression('/>STR\x{00C1}NKY</u', $this->l);
        $this->assertDoesNotMatchRegularExpression('/>NASTAVENIA</u', $this->l);
    }
    public function testReservationTabBar(): void
    {
        $this->assertStringContainsString('admin-tabs', $this->l);
        // Rezervácie tab bar = 6 tabs incl. Balíčky + Nastavenia.
        foreach (['/admin','/admin/calendar','/admin/blocked-periods','/admin/opening-hours','/admin/packages','/admin/settings'] as $h) {
            $this->assertMatchesRegularExpression(
                '/href="' . preg_quote($h, '/') . '"[^>]*class="admin-tab/',
                $this->l,
                $h . ' must be an .admin-tab in the Rezervácie tab bar'
            );
        }
        $this->assertMatchesRegularExpression('/<nav class="admin-tabs" aria-label="Rezerv\x{00E1}cie">/u', $this->l);
    }
    public function testSettingsTabBar(): void
    {
        // The "Web a systém" tab bar has Kontakt/Maintenance/Logy/GDPR/Nástroje.
        foreach (['/admin/contact','/admin/maintenance','/admin/log','/admin/gdpr','/admin/tools'] as $h) {
            $this->assertMatchesRegularExpression(
                '/href="' . preg_quote($h, '/') . '"[^>]*class="admin-tab/',
                $this->l,
                $h . ' must be an .admin-tab in the Web a systém tab bar'
            );
        }
        $this->assertMatchesRegularExpression('/<nav class="admin-tabs" aria-label="Web a syst\x{00E9}m">/u', $this->l);
    }
    public function testGroupPredicatesPresent(): void
    {
        $this->assertMatchesRegularExpression('/\$isResvGroup\s*=/', $this->l);
        $this->assertMatchesRegularExpression('/\$isPagesGroup\s*=/', $this->l);
        $this->assertMatchesRegularExpression('/\$isSettingsGroup\s*=/', $this->l);
        // Rezervácie group extended with packages + settings.
        $this->assertStringContainsString("str_starts_with(\$path, '/admin/packages')", $this->l);
        $this->assertStringContainsString("str_starts_with(\$path, '/admin/settings')", $this->l);
    }
    public function testPathNormalizedForTrailingSlash(): void
    {
        // Apache serves the admin app at "/admin/" (trailing slash). Without
        // rtrim, $isResvGroup ('/admin/' === '/admin' is false) → tab bar
        // never renders on the canonical URL. Guard that regression.
        $this->assertMatchesRegularExpression(
            '/\$path\s*=\s*rtrim\(\s*\$path\s*,\s*[\'"]\/[\'"]\s*\)/',
            $this->l,
            'layout.php must rtrim trailing slash from $path'
        );
    }
    public function testTabStylingProminent(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/admin.css');
        $this->assertMatchesRegularExpression(
            '/\.admin-tab\s*\{[^}]*border-radius/s',
            $css,
            '.admin-tab must have a prominent pill style (border-radius)'
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-tab\.is-active\s*\{[^}]*background/s',
            $css,
            'active .admin-tab must have a filled background'
        );
    }
    public function testA11yPreserved(): void
    {
        $this->assertStringContainsString('class="skip-link"', $this->l);
        $this->assertStringContainsString('id="main"', $this->l);
        $this->assertSame(1, substr_count($this->l, '<main'), 'exactly one <main>');
    }
    public function testIcalExportRemoved(): void
    {
        $this->assertStringNotContainsString('/admin/calendar.ics', $this->l, 'iCal export link must be gone');
        $this->assertStringNotContainsString('iCal export', $this->l);
    }
    public function testWebIsFirstStyledNavItem(): void
    {
        // "Web ↗" is the first sidebar item, styled like the other nav items,
        // opening the public site in a new tab.
        $this->assertMatchesRegularExpression(
            '#<nav class="admin-sidebar__nav"[^>]*>\s*<a href="/" target="_blank" rel="noopener" class="admin-nav-item admin-nav-item--top">Web#u',
            $this->l,
            'Web ↗ must be the first .admin-nav-item--top in the sidebar nav'
        );
    }
    public function testLogoutStyledNavItemPinnedBottom(): void
    {
        // Odhlásiť uses the nav-item design but lives in the footer block,
        // which the flex:1 nav pushes to the very bottom. It is now a
        // CSRF-protected POST form (button styled as the nav item), not a link.
        $this->assertMatchesRegularExpression(
            '/<button type="submit" class="admin-nav-item admin-nav-item--top admin-logout">Odhl\x{00E1}si\x{0165}/u',
            $this->l,
            'logout must be a nav-item-styled submit button'
        );
        $this->assertMatchesRegularExpression(
            '/admin-sidebar__footer[^>]*>\s*<span class="admin-user"/u',
            $this->l,
            'logout must sit in the bottom footer block'
        );
    }
}
