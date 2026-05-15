<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class GalleryRedesignTest extends TestCase
{
    public function testSectionHasRainbowAndCtaAndRadius(): void
    {
        $root = \dirname(__DIR__, 3);
        $sec = file_get_contents($root . '/private/templates/sections/galeria.php');
        $this->assertStringContainsString('rainbow', $sec);
        $this->assertStringContainsString('/galeria', $sec);
        $css = file_get_contents($root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/border-radius:\s*30px/', $css);
    }
    public function testGalleryPageTemplateExists(): void
    {
        $f = \dirname(__DIR__, 3) . '/private/templates/pages/gallery.php';
        $this->assertFileExists($f);
        $src = file_get_contents($f);
        $this->assertSame(1, substr_count($src, '<h1'), 'gallery page must have exactly one <h1');
        $this->assertStringContainsString('data-lightbox', $src);
    }
    public function testRouteRegistered(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 3) . '/public/index.php');
        $this->assertMatchesRegularExpression("#['\"]/galeria['\"]#", $idx, '/galeria route not registered');
    }
    public function testSeedHasSixthPhoto(): void
    {
        $s = file_get_contents(\dirname(__DIR__, 3) . '/private/scripts/seed-cms.php');
        // The 6th photo must be inserted by an ALWAYS-RUN idempotent block keyed on
        // sort_order = 6 — NOT only inside the empty-table (`=== 0`) guard — so it
        // also lands on existing prod DBs that already hold the original 5 rows.
        $emptyGuardPos = strpos($s, 'if ($existing === 0)');
        $this->assertNotFalse($emptyGuardPos, 'empty-table seed guard not found');

        // Idempotency guard: a COUNT/exists check keyed on sort_order = 6.
        $this->assertMatchesRegularExpression(
            '/COUNT\(\*\)[^;]*FROM\s+gallery_photos\s+WHERE\s+sort_order\s*=\s*6/i',
            $s,
            'seed must guard the 6th photo with a COUNT(...) WHERE sort_order = 6 check'
        );

        // That idempotency guard must live AFTER the empty-table block (i.e. it is
        // an always-run insert, not nested inside the `=== 0` branch).
        $guardPos = strpos($s, 'WHERE sort_order = 6');
        $this->assertNotFalse($guardPos);
        $this->assertGreaterThan(
            $emptyGuardPos,
            $guardPos,
            'sort_order = 6 idempotency guard must run AFTER (outside) the empty-table block'
        );

        // The empty-table block itself must seed only the 5 originals — otherwise
        // fresh installs would double-insert the 6th. Slice from the `=== 0` guard
        // to the close of its else-branch ("= gallery already has").
        $emptyEnd = strpos($s, '= gallery already has', $emptyGuardPos);
        $this->assertNotFalse($emptyEnd, 'empty-table else-branch not found');
        $this->assertGreaterThan(
            $emptyEnd,
            $guardPos,
            'sort_order = 6 idempotency guard must run AFTER (outside) the empty-table block'
        );
        $emptyBlock = substr($s, $emptyGuardPos, $emptyEnd - $emptyGuardPos);
        $this->assertSame(
            5,
            preg_match_all('/galeria_\d\.jpg/', $emptyBlock),
            'empty-table block must seed exactly the 5 originals (galeria_1..5)'
        );
        // ...and must NOT itself reference a 6th sort_order in that block.
        $this->assertSame(
            0,
            preg_match_all('/,\s*6\s*\]/', $emptyBlock),
            'empty-table block must not contain a sort_order=6 row'
        );
    }
}
