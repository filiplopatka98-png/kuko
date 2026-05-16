<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class AddToCalendarTest extends TestCase
{
    public function testSuccessTemplateHasGcalAndHomeOnly(): void
    {
        $t = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/pages/reservation.php');
        // Two buttons only: Google calendar + back home, side by side.
        $this->assertStringContainsString('id="cal-gcal"', $t);
        $this->assertStringContainsString('class="success-actions"', $t);
        $this->assertStringContainsString('Pridať do Google kalendára', $t);
        $this->assertStringContainsString('Späť na domov', $t);
        // The .ics affordance was removed entirely.
        $this->assertStringNotContainsString('id="cal-ics"', $t);
        $this->assertStringNotContainsString('id="success-cal"', $t);
        $this->assertStringNotContainsString('Stiahnuť .ics', $t);
        $this->assertStringNotContainsString('download=', $t);
        // Approved copy.
        $this->assertStringContainsString('Ďakujeme za rezerváciu!', $t);
        $this->assertStringContainsString('vybrali práve nás', $t);
    }

    public function testJsBuildsGcalNotIcs(): void
    {
        $js = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/js/rezervacia.js');
        $this->assertStringContainsString('calendar.google.com/calendar/render', $js);
        $this->assertStringNotContainsString('BEGIN:VCALENDAR', $js, 'ICS generation removed');
        $this->assertStringNotContainsString('text/calendar', $js);
        $this->assertStringContainsString('gcalLink.hidden = false', $js, 'gcal link revealed only when built');
    }
}
