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

    public function testLoginPostHandlerVerifiesCsrfFirst(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/admin/index.php');
        $this->assertNotFalse(
            preg_match('/\$router->post\(\'\/admin\/login\',\s*function[^{]*\{(.*?)\n\}\);/s', $idx, $m),
            'could not locate POST /admin/login closure'
        );
        $body = $m[1] ?? '';
        $this->assertNotSame('', $body, 'closure body empty');
        $posVerify = strpos($body, 'Csrf::verify');
        $posCreds  = strpos($body, "\$_POST['username']");
        $posAuth   = strpos($body, 'Auth::attempt');
        $this->assertNotFalse($posVerify, 'Csrf::verify missing in closure');
        $this->assertNotFalse($posCreds, "\$_POST['username'] missing in closure");
        $this->assertNotFalse($posAuth, 'Auth::attempt missing in closure');
        $this->assertLessThan($posCreds, $posVerify, 'CSRF must be verified before reading credentials');
        $this->assertLessThan($posAuth, $posVerify, 'CSRF must be verified before Auth::attempt');
    }
}
