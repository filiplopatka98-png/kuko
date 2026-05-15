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
            '/admin/contact' => 'Nastavenia',
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
        // A Nastavenia tab bar exists with Kontakt/Maintenance/Logy/GDPR.
        foreach (['/admin/contact','/admin/maintenance','/admin/log','/admin/gdpr'] as $h) {
            $this->assertMatchesRegularExpression(
                '/href="' . preg_quote($h, '/') . '"[^>]*class="admin-tab/',
                $this->l,
                $h . ' must be an .admin-tab in the Nastavenia tab bar'
            );
        }
        $this->assertMatchesRegularExpression('/<nav class="admin-tabs" aria-label="Nastavenia">/u', $this->l);
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
    public function testFooterDestinationsPresent(): void
    {
        foreach (['/admin/calendar.ics','/admin/logout'] as $h)
            $this->assertStringContainsString('href="' . $h . '"', $this->l);
        // Web ↗ link to public site root.
        $this->assertStringContainsString('href="/" target="_blank"', $this->l);
    }
}
