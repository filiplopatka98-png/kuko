<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class CtaPanelR5Test extends TestCase
{
    private function root(): string { return \dirname(__DIR__, 3); }
    private function read(string $rel): string { return file_get_contents($this->root() . '/' . $rel); }

    public function testNavAndFooterUseStandaloneGalleryLink(): void
    {
        foreach (['private/templates/nav.php', 'private/templates/footer.php'] as $rel) {
            $src = $this->read($rel);
            $this->assertStringContainsString('href="/galeria"', $src, "$rel must link to /galeria");
            $this->assertStringNotContainsString('href="/#galeria"', $src, "$rel must NOT link to /#galeria");
        }
    }

    public function testFaqPageHasCtaPanelAndNoBackButton(): void
    {
        $src = $this->read('private/templates/pages/faq.php');
        $this->assertStringContainsString('class="cta-panel"', $src);
        $this->assertStringContainsString('href="/rezervacia"', $src);
        $this->assertStringContainsString('Rezervovať oslavu', $src);
        $this->assertStringNotContainsString('Späť na domov', $src);
        $this->assertSame(1, substr_count($src, '<h1'), 'faq page must have exactly one <h1');
        $this->assertStringContainsString("Content::get('cta.faq.heading'", $src);
        $this->assertStringContainsString("Content::get('cta.faq.text'", $src);
    }

    public function testGalleryPageHasCtaPanelAndNoBackButton(): void
    {
        $src = $this->read('private/templates/pages/gallery.php');
        $this->assertStringContainsString('class="cta-panel"', $src);
        $this->assertStringContainsString('href="/rezervacia"', $src);
        $this->assertStringContainsString('Rezervovať oslavu', $src);
        $this->assertStringNotContainsString('Späť na domov', $src);
        $this->assertSame(1, substr_count($src, '<h1'), 'gallery page must have exactly one <h1');
        $this->assertStringContainsString("Content::get('cta.reservation.heading'", $src);
        $this->assertStringContainsString("Content::get('cta.reservation.text'", $src);
    }

    /** @return array<string,array{0:string,1:string}> key => [templateRel, fallback] */
    private function ctaDefaults(): array
    {
        return [
            'cta.faq.heading'         => ['private/templates/pages/faq.php',     'Plánujete oslavu pre svoje dieťa?'],
            'cta.faq.text'            => ['private/templates/pages/faq.php',     'Rezervujte si termín online za pár minút — vyberte balíček, dátum a čas.'],
            'cta.reservation.heading' => ['private/templates/pages/gallery.php', 'Páči sa vám u nás?'],
            'cta.reservation.text'    => ['private/templates/pages/gallery.php', 'Rezervujte si oslavu v KUKO — vyberte balíček, dátum a čas v 3 krokoch.'],
        ];
    }

    public function testCtaFallbacksAreByteIdenticalToSeed(): void
    {
        $seed = $this->read('private/scripts/seed-cms.php');
        foreach ($this->ctaDefaults() as $key => [$tplRel, $fallback]) {
            $tpl = $this->read($tplRel);
            $this->assertStringContainsString($fallback, $tpl, "fallback for $key missing in $tplRel");
            $this->assertStringContainsString($fallback, $seed, "seeded value for $key missing/mismatched in seed-cms.php");
            $this->assertStringContainsString("'$key'", $seed, "$key not seeded");
        }
    }

    public function testAdminPrefixesIncludeCta(): void
    {
        $idx = $this->read('public/admin/index.php');
        $this->assertMatchesRegularExpression(
            "/'faq'\s*=>\s*\[[^\]]*'prefixes'\s*=>\s*\[[^\]]*'cta'[^\]]*\]/s",
            $idx,
            "faq admin page must include 'cta' prefix"
        );
        $this->assertMatchesRegularExpression(
            "/'gallery'\s*=>\s*\[[^\]]*'prefixes'\s*=>\s*\[[^\]]*'cta'[^\]]*\]/s",
            $idx,
            "gallery admin page must include 'cta' prefix"
        );
    }

    public function testCssHasCtaPanelAndCenteredH1(): void
    {
        $css = $this->read('public/assets/css/main.css');
        $this->assertMatchesRegularExpression('/\.cta-panel\s*\{/', $css);
        $this->assertMatchesRegularExpression('/\.section--faq\s+h1\s*,\s*\.section--galeria\s+h1\s*\{[^}]*text-align:\s*center/s', $css);
    }
}
