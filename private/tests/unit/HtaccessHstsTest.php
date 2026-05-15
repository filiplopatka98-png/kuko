<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HtaccessHstsTest extends TestCase
{
    public function testHstsHeaderPresent(): void
    {
        $h = file_get_contents(\dirname(__DIR__, 3) . '/public/.htaccess');
        $this->assertStringContainsString('Strict-Transport-Security', $h);
        $this->assertStringContainsString('max-age=31536000', $h);
        $this->assertStringContainsString('includeSubDomains', $h);
        $this->assertStringContainsString('preload', $h);
    }
}
