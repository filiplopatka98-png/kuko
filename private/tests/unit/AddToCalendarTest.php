<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class AddToCalendarTest extends TestCase
{
    public function testSuccessTemplateHasCalendarAffordances(): void
    {
        $t = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/pages/reservation.php');
        $this->assertStringContainsString('id="success-cal"', $t);
        $this->assertStringContainsString('id="cal-ics"', $t);
        $this->assertStringContainsString('id="cal-gcal"', $t);
        $this->assertStringContainsString('download=', $t);
    }
    public function testJsBuildsIcsAndGcal(): void
    {
        $js = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/js/rezervacia.js');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $js);
        $this->assertStringContainsString('calendar.google.com/calendar/render', $js);
        $this->assertStringContainsString('text/calendar', $js);
    }
}
