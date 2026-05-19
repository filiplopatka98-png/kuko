<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class CalendarDayPanelTest extends TestCase
{
    public function testCalendarTemplateHasClickableDaysAndPanel(): void
    {
        $tpl = file_get_contents(\dirname(__DIR__, 2) . '/templates/admin/calendar.php');
        $this->assertStringContainsString('data-cal-day=', $tpl);
        $this->assertStringContainsString('role="button"', $tpl);
        $this->assertStringContainsString('id="calDayPanel"', $tpl);
        $this->assertStringContainsString('id="calDayList"', $tpl);
        $this->assertStringContainsString('var byDay', $tpl);
        $this->assertStringContainsString('admin-calendar__count', $tpl);
        // Keyboard accessible (Enter/Space) — not mouse-only.
        $this->assertMatchesRegularExpression('/keydown|keypress/', $tpl);
    }

    public function testCalendarDayPanelStyled(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/admin.css');
        $this->assertStringContainsString('.admin-day-panel', $css);
        $this->assertStringContainsString('.admin-day-list', $css);
        $this->assertStringContainsString('[data-cal-day]', $css);
    }
}
