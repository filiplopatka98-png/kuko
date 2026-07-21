<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Ú9: logout must be a CSRF-protected POST only — no GET route (a state change
 * reachable via <img>/link would be a CSRF logout), and the sidebar renders a
 * real POST form carrying the CSRF token (no inline on* handlers — CSP).
 */
final class AdminLogoutCsrfTest extends TestCase
{
    public function testNoGetLogoutRoute(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/admin/index.php');
        $this->assertStringNotContainsString("\$router->get('/admin/logout'", $idx, 'GET logout route must be removed');
    }

    public function testPostLogoutVerifiesCsrf(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/admin/index.php');
        $this->assertNotFalse(
            preg_match('/\$router->post\(\'\/admin\/logout\',\s*function[^{]*\{(.*?)\n\}\);/s', $idx, $m),
            'could not locate POST /admin/logout closure'
        );
        $body = $m[1] ?? '';
        $posVerify = strpos($body, 'Csrf::verify');
        $posLogout = strpos($body, 'Auth::logout');
        $this->assertNotFalse($posVerify, 'Csrf::verify missing in logout closure');
        $this->assertNotFalse($posLogout, 'Auth::logout missing in logout closure');
        $this->assertLessThan($posLogout, $posVerify, 'CSRF must be verified before logging out');
    }

    public function testLayoutRendersPostLogoutForm(): void
    {
        $tpl = file_get_contents(\dirname(__DIR__, 2) . '/templates/admin/layout.php');
        $this->assertStringContainsString('action="/admin/logout"', $tpl);
        $this->assertStringContainsString('method="post"', $tpl);
        $this->assertStringContainsString('Csrf::token()', $tpl);
        $this->assertStringNotContainsString('href="/admin/logout"', $tpl, 'logout must not be a plain link');
        $this->assertDoesNotMatchRegularExpression('/\son(click|submit|load|error|change|input|focus|blur|mouse\w+|key\w+)\s*=/i', $tpl, 'no inline on* handlers (CSP)');
    }
}
