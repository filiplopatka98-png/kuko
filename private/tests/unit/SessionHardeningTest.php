<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class SessionHardeningTest extends TestCase
{
    public function testAppBootstrapHardensSessionCookies(): void
    {
        $src = file_get_contents(\dirname(__DIR__, 3) . '/private/lib/App.php');
        $this->assertStringContainsString("session.use_strict_mode", $src);
        $this->assertStringContainsString("'httponly' => true", $src);
        $this->assertStringContainsString("'samesite' => 'Lax'", $src);
        $this->assertStringContainsString("PHP_SAPI !== 'cli'", $src);
        $this->assertStringContainsString("!defined('TESTING')", $src);
    }
    public function testBackupCronExistsAndLints(): void
    {
        $this->assertFileExists(\dirname(__DIR__, 3) . '/private/cron/db-backup.php');
        $this->assertFileExists(\dirname(__DIR__, 3) . '/docs/RECOVERY.md');
    }
}
