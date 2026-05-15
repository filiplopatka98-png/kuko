<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class HelperPagesNoindexTest extends TestCase
{
    public function testHelperPagesForceNoindex(): void
    {
        $root = \dirname(__DIR__, 3) . '/private/templates/pages/';
        foreach (['reservation-status.php','404.php','privacy.php','maintenance.php'] as $f) {
            $src = file_get_contents($root . $f);
            $this->assertMatchesRegularExpression('/\$pageIndexing\s*=\s*false\s*;/', $src, "$f must force \$pageIndexing=false");
        }
    }
}
