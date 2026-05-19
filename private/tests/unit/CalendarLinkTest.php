<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\CalendarLink;
use PHPUnit\Framework\TestCase;

final class CalendarLinkTest extends TestCase
{
    private array $r = [
        'package' => 'maxi', 'name' => 'Janka', 'wished_date' => '2026-06-01',
        'wished_time' => '15:00:00', 'kids_count' => 12,
        'phone' => '+421 900 1', 'email' => 'j@x.sk', 'note' => "a\nb",
    ];

    public function testBuildsGoogleTemplateUrl(): void
    {
        $u = CalendarLink::google($this->r, 120, 'Europe/Bratislava');
        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $u);
        $this->assertStringContainsString('action=TEMPLATE', $u);
        // 15:00 Europe/Bratislava (CEST, +02:00) → 13:00Z, +120min → 15:00Z.
        $this->assertStringContainsString('dates=20260601T130000Z%2F20260601T150000Z', $u);
        parse_str(parse_url($u, PHP_URL_QUERY), $q);
        $this->assertSame('MAXI — Janka', $q['text']);
        $this->assertStringContainsString('Počet detí: 12', $q['details']);
        $this->assertStringContainsString('Poznámka: a / b', $q['details']);
        $this->assertSame('Bratislavská 141, 921 01 Piešťany', $q['location']);
    }

    public function testReturnsEmptyOnUnparseableDate(): void
    {
        $bad = ['package' => 'maxi', 'name' => 'X', 'wished_date' => '', 'wished_time' => ''];
        $this->assertSame('', CalendarLink::google($bad, 120, 'Europe/Bratislava'));
    }

    public function testDurationFloorsToAtLeastOneMinute(): void
    {
        $u = CalendarLink::google($this->r, 0, 'Europe/Bratislava');
        $this->assertStringContainsString('dates=20260601T130000Z%2F20260601T130100Z', $u);
    }
}
