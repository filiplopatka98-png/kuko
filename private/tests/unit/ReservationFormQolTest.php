<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class ReservationFormQolTest extends TestCase
{
    private string $tpl;
    private string $js;

    protected function setUp(): void
    {
        $root = \dirname(__DIR__, 3);
        $this->tpl = file_get_contents($root . '/private/templates/pages/reservation.php');
        $this->js  = file_get_contents($root . '/public/assets/js/rezervacia.js');
    }

    public function testEmailDatalistPresent(): void
    {
        $this->assertStringContainsString('<datalist id="email-domains"', $this->tpl);
        $this->assertStringContainsString('list="email-domains"', $this->tpl);
    }

    public function testEmailInputStillTypeEmailRequired(): void
    {
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="f-email"[^>]*>/',
            $this->tpl
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="f-email"[^>]*type="email"|<input[^>]*type="email"[^>]*id="f-email"/',
            $this->tpl
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="f-email"[^>]*\brequired\b|<input[^>]*\brequired\b[^>]*id="f-email"/',
            $this->tpl
        );
    }

    public function testSessionStorageDraftPersist(): void
    {
        $this->assertStringContainsString('sessionStorage', $this->js);
        $this->assertStringContainsString('kuko_resv_draft', $this->js);
    }

    public function testDraftClearedOnSuccess(): void
    {
        // removeItem of the draft key must appear near the success transition
        $this->assertStringContainsString('removeItem', $this->js);
    }

    public function testNoTimeAutoPreselect(): void
    {
        // The 14:00 auto-pick was removed — the user must consciously pick a
        // start time, so no hard-coded time literal drives a default click.
        $this->assertStringNotContainsString("'14:00'", $this->js);
        $this->assertStringNotContainsString('=== \'14:00\'', $this->js);
    }
}
