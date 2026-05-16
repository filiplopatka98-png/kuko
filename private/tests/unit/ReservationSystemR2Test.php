<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class ReservationSystemR2Test extends TestCase
{
    private string $root;
    private string $tpl;
    private string $js;
    private string $css;
    private string $avail;

    protected function setUp(): void
    {
        $this->root  = \dirname(__DIR__, 3);
        $this->tpl   = file_get_contents($this->root . '/private/templates/pages/reservation.php');
        $this->js    = file_get_contents($this->root . '/public/assets/js/rezervacia.js');
        $this->css   = file_get_contents($this->root . '/public/assets/css/rezervacia.css');
        $this->avail = file_get_contents($this->root . '/private/lib/Availability.php');
    }

    public function testSummaryStepExists(): void
    {
        $this->assertStringContainsString('data-step-indicator="4"', $this->tpl, 'step 4 indicator');
        $this->assertStringContainsString('data-step="4"', $this->tpl, 'step 4 section');
        $this->assertStringContainsString('class="resv-summary"', $this->tpl, 'summary list');
        // each summary field placeholder is present
        foreach (['rs-package','rs-date','rs-time','rs-kids','rs-name','rs-phone','rs-email','rs-note'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $this->tpl, "summary field $id");
        }
        // submit lives on step 4 now; step 3 routes forward to the summary
        $this->assertStringContainsString('id="to-step-4"', $this->tpl);
        $this->assertStringContainsString('id="submit-btn"', $this->tpl);
        $this->assertSame(1, substr_count($this->tpl, '<h1'), 'exactly one h1');
    }

    public function testGdprConsentCheckboxRequired(): void
    {
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="f-gdpr"[^>]*\brequired\b|<input[^>]*\brequired\b[^>]*id="f-gdpr"/',
            $this->tpl,
            'GDPR checkbox must be required'
        );
        $this->assertStringContainsString('href="/ochrana-udajov"', $this->tpl, 'links to privacy page');
        $this->assertMatchesRegularExpression(
            "/getElementById\('f-gdpr'\)[\s\S]*?checked/",
            $this->js,
            'submit must gate on the GDPR checkbox'
        );
    }

    public function testStepIndicatorsClickable(): void
    {
        $this->assertMatchesRegularExpression(
            '/data-step-indicator="1"[^>]*role="button"[^>]*tabindex="0"/',
            $this->tpl,
            'indicators must be focusable buttons'
        );
        $this->assertStringContainsString('aria-disabled', $this->tpl, 'forward-locked indicators expose aria-disabled');
        $this->assertStringContainsString('furthestStep', $this->js, 'JS computes the furthest reachable step');
        $this->assertMatchesRegularExpression(
            "/stepIndicators\.forEach\([\s\S]*addEventListener\('click'/",
            $this->js,
            'indicators get click handlers'
        );
        $this->assertMatchesRegularExpression('/\.rezervacia__steps li\s*\{[^}]*cursor:\s*pointer/', $this->css);
    }

    public function testArrowsAreCenteredSvgNotGlyphs(): void
    {
        // No raw ‹ / › chevron glyphs left in the circular icon buttons.
        $this->assertDoesNotMatchRegularExpression(
            '/class="step__back"[^>]*>\s*[\x{2039}\x{203A}]/u',
            $this->tpl,
            'step__back must not use a text glyph'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/class="calendar__navbtn"[^>]*>\s*[\x{2039}\x{203A}]/u',
            $this->tpl,
            'calendar__navbtn must not use a text glyph'
        );
        $this->assertStringContainsString('<polyline points="15 5 8 12 15 19"', $this->tpl, 'left chevron svg');
        $this->assertStringContainsString('<polyline points="9 5 16 12 9 19"', $this->tpl, 'right chevron svg');
        $this->assertMatchesRegularExpression('/\.step__back svg\s*\{[^}]*width:\s*18px/', $this->css);
        $this->assertMatchesRegularExpression('/\.calendar__navbtn svg\s*\{[^}]*width:\s*20px/', $this->css);
        $this->assertStringNotContainsString('font-size: 1.5rem', $this->css);
        $this->assertStringNotContainsString('font-size: 1.4rem', $this->css);
    }

    public function testAvailabilityNoWholeDayPackageBlock(): void
    {
        // The package-level whole-day block is gone; blocked_full_day now only
        // comes from an admin all-day blocked_period.
        $this->assertStringNotContainsString("\$pkg['blocks_full_day']", $this->avail);
        $this->assertStringNotContainsString("\$e['blocks_full_day']", $this->avail);
        // Pending older than one month no longer holds a slot.
        $this->assertStringContainsString("modify('-1 month')", $this->avail);
        $this->assertMatchesRegularExpression(
            "/status = 'pending' AND r\.created_at >= \?/",
            $this->avail,
            'availability must ignore stale pendings'
        );
    }

    public function testExpirePendingCronExists(): void
    {
        $cron = $this->root . '/private/cron/expire-pending.php';
        $this->assertFileExists($cron);
        $src = file_get_contents($cron);
        $this->assertStringContainsString("modify('-1 month')", $src);
        $this->assertMatchesRegularExpression(
            "/UPDATE reservations[\s\S]*SET status = 'cancelled'[\s\S]*WHERE status = 'pending' AND created_at < \?/",
            $src,
            'cron must cancel pendings older than one month'
        );
    }
}
