<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use Kuko\Htpasswd;
use PHPUnit\Framework\TestCase;

final class HtpasswdTest extends TestCase
{
    public function testUpsertAddsNewUserWithVerifiableBcrypt(): void
    {
        $out = Htpasswd::upsert('', 'admin', 'Secret123');
        $this->assertMatchesRegularExpression('/^admin:\$2y\$/', trim($out));
        [$u, $h] = explode(':', trim($out), 2);
        $this->assertSame('admin', $u);
        $this->assertTrue(password_verify('Secret123', $h));
        $this->assertStringEndsWith("\n", $out);
    }

    public function testUpsertReplacesExistingUserPreservingOthers(): void
    {
        $existing = "alice:\$2y\$10\$aaaaaaaaaaaaaaaaaaaaaeUGiSb3p4Q1bq1Qe0\nbob:OLDHASH\n";
        $out = Htpasswd::upsert($existing, 'bob', 'NewPass!');
        $this->assertStringContainsString('alice:$2y$10$aaaaaaaaaaaaaaaaaaaaaeUGiSb3p4Q1bq1Qe0', $out);
        $this->assertStringNotContainsString('OLDHASH', $out);
        $lines = array_values(array_filter(explode("\n", trim($out))));
        $this->assertCount(2, $lines, 'must still have exactly 2 users');
        $map = [];
        foreach ($lines as $l) { [$u,$hh] = explode(':', $l, 2); $map[$u] = $hh; }
        $this->assertTrue(password_verify('NewPass!', $map['bob']));
    }

    public function testUpsertTrimsUsernameAndRejectsColonOrEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Htpasswd::upsert('', 'bad:user', 'x');
    }

    public function testUpsertRejectsEmptyUsername(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Htpasswd::upsert('', '   ', 'x');
    }
}
