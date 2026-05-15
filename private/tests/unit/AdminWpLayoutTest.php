<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class AdminWpLayoutTest extends TestCase
{
    private string $l;
    protected function setUp(): void { $this->l = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/admin/layout.php'); }
    public function testSidebarAndGroups(): void
    {
        $this->assertStringContainsString('admin-sidebar', $this->l);
        $this->assertMatchesRegularExpression('/STR\x{00C1}NKY/u', $this->l);
        $this->assertMatchesRegularExpression('/NASTAVENIA/u', $this->l);
        $this->assertMatchesRegularExpression('/Rezerv\x{00E1}cie/u', $this->l);
    }
    public function testReservationTabs(): void
    {
        $this->assertStringContainsString('admin-tabs', $this->l);
        foreach (['/admin/calendar','/admin/blocked-periods','/admin/opening-hours'] as $h)
            $this->assertStringContainsString('href="' . $h . '"', $this->l);
    }
    public function testReservationsSingleSidebarItemWithVisibleTabs(): void
    {
        // Rezervácie is ONE top-level sidebar item (the tabs are its sub-nav).
        $this->assertMatchesRegularExpression(
            '/class="admin-nav-item admin-nav-item--top[^"]*">Rezerv\x{00E1}cie/u',
            $this->l,
            'Rezervácie must be a single top-level sidebar item'
        );
        // The 4 reservation tabs are rendered as .admin-tab in the tab bar.
        foreach (['/admin','/admin/calendar','/admin/blocked-periods','/admin/opening-hours'] as $h) {
            $this->assertMatchesRegularExpression(
                '/href="' . preg_quote($h, '/') . '"[^>]*class="admin-tab/',
                $this->l,
                $h . ' must be an .admin-tab'
            );
        }
        // The tab styling must be prominent (pill: border-radius), not the
        // old subtle text-only underline that the user could not see.
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
    public function testA11yPreserved(): void
    {
        $this->assertStringContainsString('class="skip-link"', $this->l);
        $this->assertStringContainsString('id="main"', $this->l);
        $this->assertSame(1, substr_count($this->l, '<main'), 'exactly one <main>');
    }
    public function testAllDestinationsPresent(): void
    {
        foreach (['/admin/content','/admin/packages','/admin/gallery','/admin/contact','/admin/seo','/admin/maintenance','/admin/log','/admin/gdpr','/admin/settings','/admin/calendar.ics','/admin/logout'] as $h)
            $this->assertStringContainsString('href="' . $h . '"', $this->l);
    }
}
