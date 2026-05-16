<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class OslavyBadgeIconsTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = \dirname(__DIR__, 3); }
    public function testBadgeSvgsExistAndWhite(): void
    {
        foreach (['badge-balloon.svg','badge-balloons.svg','badge-crown.svg'] as $f) {
            $p = $this->root . '/public/assets/icons/' . $f;
            $this->assertFileExists($p);
            $svg = file_get_contents($p);
            $this->assertStringContainsString('#fff', $svg, "$f must be white-filled");
            $this->assertStringNotContainsString('#c9a8e1', $svg, "$f must not be the purple O-nas color");
        }
    }
    public function testOslavyMapsCorrectBadges(): void
    {
        $t = file_get_contents($this->root . '/private/templates/sections/oslavy.php');
        $this->assertStringContainsString('badge-balloon.svg', $t);
        $this->assertStringContainsString('badge-balloons.svg', $t);
        $this->assertStringContainsString('badge-crown.svg', $t);
        // old wrong mappings gone
        $this->assertStringNotContainsString('/assets/icons/uzavreta.svg', $t);
    }
    public function testBadgeSolidColoredBackground(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/\.package--blue\s+\.package__badge\s*\{[^}]*background:\s*var\(--c-blue\)/s', $css);
        $this->assertMatchesRegularExpression('/\.package--yellow\s+\.package__badge\s*\{[^}]*background:\s*var\(--c-yellow\)/s', $css);
    }
}
