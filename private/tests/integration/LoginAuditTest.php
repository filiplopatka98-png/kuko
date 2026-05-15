<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class LoginAuditTest extends TestCase
{
    public function testLoginHandlerLogsAuthEvents(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/admin/index.php');
        $this->assertStringContainsString("'login_ok'", $idx);
        $this->assertStringContainsString("'login_fail'", $idx);
        $this->assertStringContainsString("'login_locked'", $idx);
        $this->assertStringContainsString("'auth'", $idx);
        $this->assertMatchesRegularExpression('/HTTP_USER_AGENT.{0,160}substr|substr.{0,160}HTTP_USER_AGENT/s', $idx);
    }
}
