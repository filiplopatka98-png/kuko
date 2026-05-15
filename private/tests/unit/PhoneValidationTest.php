<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Reservation;
use PHPUnit\Framework\TestCase;

final class PhoneValidationTest extends TestCase
{
    private function base(): array
    {
        return [
            'package'      => 'mini',
            'wished_date'  => date('Y-m-d', strtotime('+7 days')),
            'wished_time'  => '14:00',
            'kids_count'   => 8,
            'name'         => 'Jana Mrkvičková',
            'phone'        => '+421915319934',
            'email'        => 'jana@example.com',
            'note'         => '',
        ];
    }

    /** @return iterable<string,array{0:string}> */
    public static function validPhones(): iterable
    {
        yield '+421 no spaces'   => ['+421915319934'];
        yield '+421 with spaces' => ['+421 915 319 934'];
        yield '0 prefix'         => ['0915319934'];
        yield '0 prefix spaced'  => ['0915 319 934'];
        yield '0 prefix mixed'   => ['0915/319-934'];
    }

    /** @return iterable<string,array{0:string}> */
    public static function invalidPhones(): iterable
    {
        yield 'too short'        => ['12345'];
        yield '+421 too short'   => ['+4219153199'];
        yield '00421 prefix'     => ['00421915319934'];
        yield '+421 too long'    => ['+421915319934567'];
        yield 'letters'          => ['abcdefghij'];
        yield 'empty'            => [''];
    }

    /** @dataProvider validPhones */
    public function testValidPhonePasses(string $phone): void
    {
        $d = $this->base();
        $d['phone'] = $phone;
        $errors = Reservation::validate($d);
        $this->assertArrayNotHasKey('phone', $errors, "expected '$phone' to be a valid SK phone");
    }

    /** @dataProvider invalidPhones */
    public function testInvalidPhoneFails(string $phone): void
    {
        $d = $this->base();
        $d['phone'] = $phone;
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('phone', $errors, "expected '$phone' to be rejected");
    }

    public function testErrorMessageIsSlovak(): void
    {
        $d = $this->base();
        $d['phone'] = '12345';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('phone', $errors);
        $this->assertStringContainsString('slovenské', $errors['phone']);
    }
}
