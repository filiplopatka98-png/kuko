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
