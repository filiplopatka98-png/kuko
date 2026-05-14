<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Reservation;
use PHPUnit\Framework\TestCase;

final class ReservationTest extends TestCase
{
    private function valid(): array
    {
        return [
            'package'      => 'mini',
            'wished_date'  => date('Y-m-d', strtotime('+7 days')),
            'wished_time'  => '14:00',
            'kids_count'   => 8,
            'name'         => 'Jana Mrkvičková',
            'phone'        => '+421915123456',
            'email'        => 'jana@example.com',
            'note'         => 'Téma: pirátska oslava',
        ];
    }

    public function testValidPasses(): void
    {
        $errors = Reservation::validate($this->valid());
        $this->assertSame([], $errors);
    }

    public function testInvalidPackage(): void
    {
        $d = $this->valid(); $d['package'] = 'unknown';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('package', $errors);
    }

    public function testDateInPast(): void
    {
        $d = $this->valid(); $d['wished_date'] = '2020-01-01';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('wished_date', $errors);
    }

    public function testDateInvalidFormat(): void
    {
        $d = $this->valid(); $d['wished_date'] = '01.05.2026';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('wished_date', $errors);
    }

    public function testKidsRange(): void
    {
        foreach ([0, -1, 51, 999] as $bad) {
            $d = $this->valid(); $d['kids_count'] = $bad;
            $errors = Reservation::validate($d);
            $this->assertArrayHasKey('kids_count', $errors, "expected fail for kids_count=$bad");
        }
    }

    public function testEmailRequired(): void
    {
        $d = $this->valid(); $d['email'] = 'not-an-email';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testPhoneFormat(): void
    {
        $d = $this->valid(); $d['phone'] = 'abc';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('phone', $errors);
    }

    public function testNoteOptional(): void
    {
        $d = $this->valid(); unset($d['note']);
        $errors = Reservation::validate($d);
        $this->assertSame([], $errors);
    }

    public function testNoteTooLong(): void
    {
        $d = $this->valid(); $d['note'] = str_repeat('x', 1500);
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('note', $errors);
    }

    public function testTimeFormat(): void
    {
        $d = $this->valid(); $d['wished_time'] = '25:00';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('wished_time', $errors);
    }

    public function testNameTooShort(): void
    {
        $d = $this->valid(); $d['name'] = 'A';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('name', $errors);
    }
}
