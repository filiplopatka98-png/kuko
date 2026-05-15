<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class SingleH1Test extends TestCase
{
    /** @dataProvider pages */
    public function testExactlyOneH1(string $file): void
    {
        $src = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/pages/' . $file);
        $this->assertSame(1, substr_count($src, '<h1'), "$file must have exactly one <h1");
    }
    public static function pages(): array
    {
        // home.php intentionally excluded: its single <h1> lives in the
        // included sections/hero.php, so home.php itself has zero <h1>.
        return [['reservation.php'], ['404.php'], ['reservation-status.php'], ['maintenance.php']];
    }
}
