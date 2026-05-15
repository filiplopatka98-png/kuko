<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class EditablePagesTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = \dirname(__DIR__, 3); }
    public function testPrivacyUsesContentBlock(): void
    {
        $t = file_get_contents($this->root . '/private/templates/pages/privacy.php');
        $this->assertStringContainsString("Content::get('privacy.body'", $t);
        $this->assertSame(1, substr_count($t, '<h1'), 'privacy keeps one h1');
    }
    public function testFaqUsesContentBlocks(): void
    {
        $t = file_get_contents($this->root . '/private/templates/pages/faq.php');
        // faq.intro stays a content block; faq.items is now a structured
        // repeater rendered via the Faq helper (not a Quill content block).
        $this->assertStringContainsString("Content::get('faq.intro'", $t);
        $this->assertStringContainsString('Faq::items', $t);
        $this->assertStringNotContainsString("Content::get('faq.items'", $t);
        $this->assertSame(1, substr_count($t, '<h1'), 'faq keeps one h1');
    }
    public function testSeedListsNewBlocks(): void
    {
        $s = file_get_contents($this->root . '/private/scripts/seed-cms.php');
        foreach (['privacy.body','faq.intro','faq.items'] as $k) {
            $this->assertStringContainsString($k, $s);
        }
    }
}
