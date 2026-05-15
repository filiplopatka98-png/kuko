<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerExtendedTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        // dirname(__DIR__,3) from private/tests/unit/ == repo root
        $this->root = \dirname(__DIR__, 3);
    }

    public function testKeepsDetailsSummary(): void
    {
        $in  = '<details><summary>Q</summary><p>A</p></details>';
        $out = HtmlSanitizer::clean($in);
        $this->assertStringContainsString('<details>', $out);
        $this->assertStringContainsString('<summary>Q</summary>', $out);
        $this->assertStringContainsString('<p>A</p>', $out);
    }

    public function testKeepsDivWithClass(): void
    {
        $out = HtmlSanitizer::clean('<div class="faq"><p>x</p></div>');
        $this->assertStringContainsString('<div class="faq">', $out);
        $this->assertStringContainsString('<p>x</p>', $out);
    }

    public function testKeepsH2WithClass(): void
    {
        $out = HtmlSanitizer::clean('<h2 class="legal-h2">X</h2>');
        $this->assertStringContainsString('<h2 class="legal-h2">X</h2>', $out);
    }

    public function testKeepsDetailsClass(): void
    {
        $out = HtmlSanitizer::clean('<details class="faq__item"><summary>S</summary></details>');
        $this->assertStringContainsString('class="faq__item"', $out);
    }

    /** @dataProvider goodHrefProvider */
    public function testKeepsSafeHrefs(string $href): void
    {
        $out = HtmlSanitizer::clean('<a href="' . $href . '">x</a>');
        $this->assertStringContainsString('href="' . $href . '"', $out);
    }

    public static function goodHrefProvider(): array
    {
        return [
            'root-relative'      => ['/rezervacia'],
            'root-with-fragment' => ['/#kontakt'],
            'root'               => ['/'],
            'fragment'           => ['#top'],
            'dot-relative'       => ['./page'],
            'https'              => ['https://e.sk'],
            'http'               => ['http://e.sk'],
            'mailto'             => ['mailto:a@b.sk'],
            'tel'                => ['tel:+421900'],
        ];
    }

    /** @dataProvider badHrefProvider */
    public function testStripsDangerousHrefs(string $href, string $needle): void
    {
        $out = HtmlSanitizer::clean('<a href="' . $href . '">x</a>');
        $this->assertStringNotContainsStringIgnoringCase($needle, $out);
        // anchor itself and text survive (only the href attr is dropped)
        $this->assertStringContainsString('>x</a>', $out);
    }

    public static function badHrefProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)', 'javascript:'],
            'data'       => ['data:text/html,<b>', 'data:text/html'],
            'vbscript'   => ['vbscript:msgbox(1)', 'vbscript:'],
        ];
    }

    public function testStripsScript(): void
    {
        $out = HtmlSanitizer::clean('<p>ok</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('<p>ok</p>', $out);
    }

    public function testStripsImgAndOnError(): void
    {
        $out = HtmlSanitizer::clean('<p><img onerror=alert(1) src=x>txt</p>');
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $out);
        $this->assertStringContainsString('txt', $out);
    }

    public function testStripsStyleAttribute(): void
    {
        $out = HtmlSanitizer::clean('<p style="x">t</p>');
        $this->assertStringNotContainsString('style=', $out);
        $this->assertStringContainsString('<p>t</p>', $out);
    }

    public function testStripsStyleOnNewlyAllowedH2(): void
    {
        $out = HtmlSanitizer::clean('<h2 style="text-align:left;">T</h2>');
        $this->assertStringNotContainsString('style=', $out);
        $this->assertStringContainsString('<h2>T</h2>', $out);
    }

    public function testStripsIframeAndObject(): void
    {
        $out = HtmlSanitizer::clean('<iframe src="//evil"></iframe><object data="x"></object><p>k</p>');
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringNotContainsString('<object', $out);
        $this->assertStringContainsString('<p>k</p>', $out);
    }

    /**
     * Key regression guard: the verbatim faq.items block markup must round-trip
     * losslessly (structural elements + relative links preserved).
     */
    public function testFaqItemsRoundTripLossless(): void
    {
        $faq = $this->extractFallback($this->root . '/private/templates/pages/faq.php', 'faq.items');
        $out = HtmlSanitizer::clean($faq);
        $this->assertStringContainsString('<div class="faq">', $out);
        $this->assertStringContainsString('<details class="faq__item">', $out);
        $this->assertStringContainsString('<summary>Aké sú ceny vstupu do KUKO?</summary>', $out);
        $this->assertStringContainsString('href="/rezervacia"', $out);
        $this->assertStringContainsString('href="/#kontakt"', $out);
        $this->assertStringContainsString('href="tel:+421915319934"', $out);
        $this->assertStringContainsString('href="mailto:info@kuko-detskysvet.sk"', $out);
        $this->assertSame(6, substr_count($out, '<details class="faq__item">'));
    }

    /**
     * Key regression guard: the verbatim privacy.body block markup must round-trip
     * losslessly (h2.legal-h2 headings + links preserved, no inline style needed).
     */
    public function testPrivacyBodyRoundTripLossless(): void
    {
        $body = $this->extractFallback($this->root . '/private/templates/pages/privacy.php', 'privacy.body');
        $out  = HtmlSanitizer::clean($body);
        $this->assertStringContainsString('<h2 class="legal-h2">1. Prevádzkovateľ</h2>', $out);
        $this->assertStringContainsString('<h2 class="legal-h2">6. Vaše práva</h2>', $out);
        $this->assertStringContainsString('href="mailto:info@kuko-detskysvet.sk"', $out);
        $this->assertStringContainsString('href="https://policies.google.com/privacy"', $out);
        $this->assertStringContainsString('href="/"', $out);
        $this->assertStringContainsString('<ul>', $out);
        $this->assertStringContainsString('<li>', $out);
        $this->assertSame(6, substr_count($out, '<h2 class="legal-h2">'));
        // inline style must be gone after the class migration + sanitize
        $this->assertStringNotContainsString('style=', $out);
    }

    public function testPrivacyTemplateHasNoInlineStyleInBlock(): void
    {
        $body = $this->extractFallback($this->root . '/private/templates/pages/privacy.php', 'privacy.body');
        $this->assertStringNotContainsString('style=', $body, 'privacy.body fallback must use CSS classes, not inline style');
        $this->assertStringContainsString('class="legal-h2"', $body);
    }

    public function testSeedAndTemplateFallbackByteIdentical(): void
    {
        $tpl  = $this->extractFallback($this->root . '/private/templates/pages/privacy.php', 'privacy.body');
        $seed = $this->extractFallback($this->root . '/private/scripts/seed-cms.php', 'privacy.body');
        $this->assertSame($tpl, $seed, 'privacy.body seed value and template fallback must be byte-identical');

        $tplF  = $this->extractFallback($this->root . '/private/templates/pages/faq.php', 'faq.items');
        $seedF = $this->extractFallback($this->root . '/private/scripts/seed-cms.php', 'faq.items');
        $this->assertSame($tplF, $seedF, 'faq.items seed value and template fallback must be byte-identical');
    }

    /**
     * Pull a heredoc fallback/seed value for the given block key out of a PHP
     * source file. Matches both Content::get('key', <<<'HTML' ... HTML) and
     * ['key', ..., <<<'HTML' ... HTML].
     */
    private function extractFallback(string $file, string $key): string
    {
        $src = file_get_contents($file);
        $this->assertNotFalse($src, "cannot read $file");
        $q = preg_quote($key, '/');
        // capture the heredoc body following the first occurrence of the key
        if (!preg_match('/' . $q . '.*?<<<\'HTML\'\R(.*?)\R\s*HTML/s', $src, $m)) {
            $this->fail("could not extract heredoc for $key from $file");
        }
        return $m[1];
    }
}
