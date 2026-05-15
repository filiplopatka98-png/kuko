<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class LoginBrandingTest extends TestCase
{
    public function testLoginUsesKukoPaletteNotGenericBlue(): void
    {
        $src = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/admin/login.php');
        $this->assertStringContainsString('#D88BBE', $src, 'login must use KUKO accent');
        $this->assertStringNotContainsString('#5e72e4', $src, 'generic blue must be gone');
        $this->assertStringContainsString("admin.css", $src, 'login should pull admin.css via \$stylesheets');
    }
}
