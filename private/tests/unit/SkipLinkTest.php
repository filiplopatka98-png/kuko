<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class SkipLinkTest extends TestCase
{
    /** @dataProvider layouts */
    public function testLayoutHasSkipLinkAndMain(string $rel): void
    {
        $src = file_get_contents(\dirname(__DIR__, 3) . $rel);
        $this->assertStringContainsString('class="skip-link"', $src, "$rel missing skip link");
        $this->assertStringContainsString('href="#main"', $src, "$rel skip link must target #main");
        $this->assertMatchesRegularExpression('/id="main"/', $src, "$rel must expose id=\"main\"");
    }
    public static function layouts(): array
    {
        return [
            ['/private/templates/layout.php'],
            ['/private/templates/layout-minimal.php'],
            ['/private/templates/admin/layout.php'],
        ];
    }
    public function testSkipLinkStyled(): void
    {
        foreach (['/public/assets/css/main.css','/public/assets/css/admin.css'] as $f) {
            $this->assertStringContainsString('.skip-link', file_get_contents(\dirname(__DIR__, 3) . $f), "$f missing .skip-link style");
        }
    }
}
