<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthHtpasswdPathTest extends TestCase
{
    public function testAuthReadsHtpasswdFromConfigDirNotWebroot(): void
    {
        $src = file_get_contents(\dirname(__DIR__, 3) . '/private/lib/Auth.php');
        $this->assertStringContainsString("/config/.htpasswd", $src);
        $this->assertStringNotContainsString("/public/admin/.htpasswd", $src);
    }
}
