<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Recaptcha;
use Kuko\HttpClient;
use PHPUnit\Framework\TestCase;

final class RecaptchaTest extends TestCase
{
    private function client(array $response): HttpClient
    {
        return new class($response) implements HttpClient {
            public function __construct(private array $resp) {}
            public function postForm(string $url, array $params): array { return $this->resp; }
        };
    }

    public function testValidScore(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => true, 'score' => 0.9, 'action' => 'reservation']));
        $result = $r->verify('token', 'reservation');
        $this->assertTrue($result->ok);
        $this->assertSame(0.9, $result->score);
    }

    public function testLowScore(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => true, 'score' => 0.2, 'action' => 'reservation']));
        $this->assertFalse($r->verify('token', 'reservation')->ok);
    }

    public function testFailedResponse(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => false, 'error-codes' => ['invalid-input-response']]));
        $this->assertFalse($r->verify('bad', 'reservation')->ok);
    }

    public function testActionMismatch(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => true, 'score' => 0.9, 'action' => 'other']));
        $this->assertFalse($r->verify('token', 'reservation')->ok);
    }

    public function testEmptySecretBypasses(): void
    {
        $r = new Recaptcha('', 0.5, $this->client(['success' => false]));
        $this->assertTrue($r->verify('any', 'reservation')->ok);
    }
}
