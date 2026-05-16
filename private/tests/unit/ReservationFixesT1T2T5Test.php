<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class ReservationFixesT1T2T5Test extends TestCase
{
    private string $root;
    private string $tpl;
    private string $js;
    private string $css;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 3);
        $this->tpl  = file_get_contents($this->root . '/private/templates/pages/reservation.php');
        $this->js   = file_get_contents($this->root . '/public/assets/js/rezervacia.js');
        $this->css  = file_get_contents($this->root . '/public/assets/css/rezervacia.css');
    }

    /** T1: the fragile hash/popstate step routing is gone (it made the
     *  thank-you step revert to step 1), success still reached via goStep. */
    public function testNoHashPopstateRouting(): void
    {
        // No popstate listener and no hash assignment drive step state
        // (a comment may still mention them — assert the behavioral code).
        $this->assertStringNotContainsString("addEventListener('popstate'", $this->js, 'no popstate step routing');
        $this->assertStringNotContainsString('location.hash =', $this->js, 'no hash assignment for step state');
        $this->assertStringNotContainsString('location.hash=', $this->js);
        $this->assertStringContainsString("goStep('success')", $this->js, 'success step still reached on submit');
        $this->assertStringContainsString('data-step="success"', $this->tpl, 'success step still exists');
    }

    /** T2: custom inline validation, no native browser bubbles. */
    public function testCustomValidationNoNativeBubbles(): void
    {
        $this->assertStringNotContainsString('reportValidity', $this->js, 'must not use native reportValidity bubble');
        $this->assertStringNotContainsString('checkValidity', $this->js, 'must not rely on native checkValidity');
        $this->assertStringContainsString('function fieldError', $this->js, 'custom per-field validator');
        $this->assertStringContainsString('function validateStep3', $this->js);
        $this->assertStringContainsString("setAttribute('aria-invalid'", $this->js);
        foreach (['f-kids', 'f-name', 'f-phone', 'f-email'] as $id) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]*id="' . $id . '"[^>]*aria-describedby="err-' . $id . '"/',
                $this->tpl,
                "$id must be described by its error element"
            );
            $this->assertStringContainsString('id="err-' . $id . '"', $this->tpl, "error box for $id");
        }
        $this->assertStringContainsString('class="field__error"', $this->tpl);
        $this->assertMatchesRegularExpression('/\.field__error\s*\{/', $this->css);
        $this->assertMatchesRegularExpression('/\.field\.has-error\s+input/', $this->css);
    }

    /** T5: .sr-only must be defined in rezervacia.css (this page uses
     *  layout-minimal which does NOT load main.css). */
    public function testSrOnlyDefinedInReservationCss(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.sr-only\s*\{[^}]*position:\s*absolute[^}]*clip:\s*rect\(0,0,0,0\)/s',
            $this->css,
            '.sr-only must be visually-hidden in rezervacia.css'
        );
    }
}
