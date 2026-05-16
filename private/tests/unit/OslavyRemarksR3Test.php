<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class OslavyRemarksR3Test extends TestCase
{
    private string $root;
    private string $t;
    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 3);
        $this->t = file_get_contents($this->root . '/private/templates/sections/oslavy.php');
    }

    public function testMetaIconsUseAssetSvgs(): void
    {
        $this->assertStringContainsString('little-kid.svg', $this->t);
        $this->assertStringContainsString('clock.svg', $this->t);
    }

    public function testNoEmojiMetaIcons(): void
    {
        $this->assertStringNotContainsString('👶', $this->t);
        $this->assertStringNotContainsString('⏰', $this->t);
    }

    public function testNoLiteralCheckmarkInInclList(): void
    {
        $this->assertStringNotContainsString('✓', $this->t);
    }

    public function testNoteBlockPresent(): void
    {
        $this->assertStringContainsString("Content::get('oslavy.note'", $this->t);
        $this->assertStringContainsString('oslavy__note', $this->t);
    }

    public function testBadgeBorderRingRemoved(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertDoesNotMatchRegularExpression(
            '/\.package__badge\s*\{[^}]*border:\s*4px solid var\(--bg-cream\)/s',
            $css,
            'package__badge must not keep the cream border ring'
        );
    }

    public function testInclBulletPseudoElement(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertMatchesRegularExpression(
            '/\.package__incl li::before\s*\{[^}]*background:\s*var\(--c-accent\)/s',
            $css
        );
    }

    public function testNoteCssRulePresent(): void
    {
        $css = file_get_contents($this->root . '/public/assets/css/main.css');
        $this->assertStringContainsString('.oslavy__note', $css);
    }

    public function testSeedHasOslavyNote(): void
    {
        $seed = file_get_contents($this->root . '/private/scripts/seed-cms.php');
        $this->assertStringContainsString("'oslavy.note'", $seed);
    }

    public function testNoteFallbackMatchesSeedValue(): void
    {
        $expected = '*Konečná cena závisí od možností prispôsobenia - Každý balíček si môžete upraviť podľa vašich predstáv: predĺženie času oslavy, výzdoba na mieru (téma, farby), catering pre deti aj rodičov, torta alebo sweet bar, špeciálne požiadavky…';
        $this->assertStringContainsString($expected, $this->t, 'template fallback must contain exact note text');
        $seed = file_get_contents($this->root . '/private/scripts/seed-cms.php');
        $this->assertStringContainsString($expected, $seed, 'seed must contain exact note text');
    }

    public function testOslavyPrefixEditableInAdmin(): void
    {
        $admin = file_get_contents($this->root . '/public/admin/index.php');
        $this->assertMatchesRegularExpression(
            "/'home'\s*=>.*'prefixes'\s*=>\s*\[[^\]]*'oslavy'[^\]]*\]/s",
            $admin
        );
    }
}
