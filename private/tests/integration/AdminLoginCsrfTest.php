<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class AdminLoginCsrfTest extends TestCase
{
    public function testLoginTemplateContainsCsrfField(): void
    {
        $tpl = file_get_contents(\dirname(__DIR__, 2) . '/templates/admin/login.php');
        $this->assertStringContainsString('name="csrf"', $tpl);
        $this->assertStringContainsString('Csrf::token()', $tpl);
    }

    public function testLoginPostHandlerVerifiesCsrf(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/admin/index.php');
        $this->assertMatchesRegularExpression(
            '/post\(.\/admin\/login.*?Csrf::verify.*?Auth::attempt/s',
            $idx
        );
    }
}
