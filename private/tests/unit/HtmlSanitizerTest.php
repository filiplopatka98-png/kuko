<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testAllowsWhitelistedTags(): void
    {
        $in = '<p>Hello <strong>bold</strong> <em>it</em> <a href="https://x.sk">link</a></p>';
        $this->assertSame($in, HtmlSanitizer::clean($in));
    }

    public function testStripsScript(): void
    {
        $out = HtmlSanitizer::clean('<p>ok</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('<p>ok</p>', $out);
    }

    public function testStripsOnAttributes(): void
    {
        $out = HtmlSanitizer::clean('<a href="https://x.sk" onclick="evil()">x</a>');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('href="https://x.sk"', $out);
    }

    public function testStripsJavascriptHref(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function testAllowsMailtoTel(): void
    {
        $in = '<a href="mailto:a@b.sk">m</a><a href="tel:+421900">t</a>';
        $out = HtmlSanitizer::clean($in);
        $this->assertStringContainsString('mailto:a@b.sk', $out);
        $this->assertStringContainsString('tel:+421900', $out);
    }

    public function testStripsDisallowedTagKeepsText(): void
    {
        $out = HtmlSanitizer::clean('<div><span>keep</span></div>');
        $this->assertStringContainsString('keep', $out);
        $this->assertStringNotContainsString('<div>', $out);
        $this->assertStringNotContainsString('<span>', $out);
    }

    public function testAllowsListsAndHeadings(): void
    {
        $in = '<h3>T</h3><ul><li>a</li><li>b</li></ul><ol><li>c</li></ol>';
        $this->assertSame($in, HtmlSanitizer::clean($in));
    }

    public function testEmptyStringSafe(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(''));
    }
}
