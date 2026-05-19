<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class CookieConsentTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = \dirname(__DIR__, 3); }

    public function testCookiePolicyPageWired(): void
    {
        $idx = file_get_contents($this->root . '/public/index.php');
        $this->assertStringContainsString("\$router->get('/zasady-cookies'", $idx, 'route registered');
        $this->assertStringContainsString("'/zasady-cookies'", $idx); // also in sitemap list
        $this->assertFileExists($this->root . '/private/templates/pages/cookies.php');
        $tpl = file_get_contents($this->root . '/private/templates/pages/cookies.php');
        $this->assertMatchesRegularExpression('/\$pageType\s*=\s*[\'"]cookies[\'"]/', $tpl);
        $this->assertStringContainsString("Content::get('cookies.body'", $tpl);
        $this->assertStringNotContainsString('<table', $tpl, 'no table — HtmlSanitizer would strip it');
        $footer = file_get_contents($this->root . '/private/templates/footer.php');
        $this->assertStringContainsString('href="/zasady-cookies"', $footer);
        $seed = file_get_contents($this->root . '/private/scripts/seed-cms.php');
        $this->assertStringContainsString("'cookies.body'", $seed);
        $this->assertStringContainsString("'seo.cookies.title'", $seed);
        $admin = file_get_contents($this->root . '/public/admin/index.php');
        $this->assertMatchesRegularExpression("/'cookies'\s*=>\s*\[[^\]]*'\/zasady-cookies'/", $admin);
    }

    public function testStandaloneConsentModule(): void
    {
        $js = file_get_contents($this->root . '/public/assets/js/cookie-consent.js');
        // single source of truth, JSON v2 model, legacy migration
        $this->assertStringContainsString("'kuko_cookie_consent'", $js);
        $this->assertStringContainsString('v: 2', $js);
        $this->assertStringContainsString("=== 'accepted'", $js);
        $this->assertStringContainsString("=== 'denied'", $js);
        $this->assertStringContainsString("kuko:consent", $js);
        foreach (['recaptcha', 'analytics', 'marketing'] as $cat) {
            $this->assertStringContainsString($cat, $js);
        }
        // loaded by the banner partial (works on full + minimal layout)
        $banner = file_get_contents($this->root . '/private/templates/cookie-banner.php');
        $this->assertStringContainsString('/assets/js/cookie-consent.js', $banner);
        $this->assertStringContainsString('data-cookie-action="settings"', $banner);
        $this->assertStringContainsString('data-cookie-action="save"', $banner);
        $this->assertStringContainsString('data-cookie-action="accept"', $banner);
        $this->assertStringContainsString('data-cookie-action="deny"', $banner);
        $this->assertStringContainsString('href="/zasady-cookies"', $banner);
        foreach (['recaptcha', 'analytics', 'marketing'] as $cat) {
            $this->assertStringContainsString('data-cookie-cat="' . $cat . '"', $banner);
        }
        // a disabled, pre-checked "necessary" toggle
        $this->assertMatchesRegularExpression('/<input type="checkbox" checked disabled>/', $banner);
        // Settings live in a SEPARATE modal dialog, not crammed in the banner.
        $this->assertStringContainsString('id="cookie-modal"', $banner);
        $this->assertStringContainsString('aria-modal="true"', $banner);
        $this->assertStringContainsString('data-cookie-action="close"', $banner);
        $this->assertStringNotContainsString('id="cookie-settings"', $banner, 'old inline panel removed');
        $this->assertStringContainsString('function openModal', $js);
        $this->assertStringContainsString('function closeModal', $js);
        $this->assertStringContainsString("'Escape'", $js);
        // build pipeline minifies it
        $build = file_get_contents($this->root . '/private/scripts/build-assets.php');
        $this->assertStringContainsString('/assets/js/cookie-consent.js', $build);
    }

    public function testOldInlineCookieCodeRemoved(): void
    {
        $main = file_get_contents($this->root . '/public/assets/js/main.js');
        $this->assertStringNotContainsString("localStorage.setItem(CONSENT_KEY", $main);
        $this->assertStringNotContainsString('export function getConsent', $main);
        // reservation page only READS consent now (no banner writing of its own)
        $rez = file_get_contents($this->root . '/public/assets/js/rezervacia.js');
        $this->assertStringNotContainsString("localStorage.setItem('kuko_cookie_consent'", $rez);
        $this->assertMatchesRegularExpression('/function consentAccepted\(\)[\s\S]*JSON\.parse/', $rez);
    }
}
