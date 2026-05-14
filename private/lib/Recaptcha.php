<?php
declare(strict_types=1);
namespace Kuko;

final class RecaptchaResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?float $score,
        public readonly ?string $reason,
    ) {}
}

final class Recaptcha
{
    public function __construct(
        private string $secret,
        private float $minScore,
        private HttpClient $http,
    ) {}

    public function verify(string $token, string $expectedAction): RecaptchaResult
    {
        if ($this->secret === '') {
            return new RecaptchaResult(true, 1.0, 'no-secret-bypass');
        }
        $r = $this->http->postForm('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $this->secret,
            'response' => $token,
        ]);
        if (empty($r['success'])) {
            return new RecaptchaResult(false, null, 'failed:' . implode(',', (array)($r['error-codes'] ?? [])));
        }
        if (($r['action'] ?? '') !== $expectedAction) {
            return new RecaptchaResult(false, (float)($r['score'] ?? 0), 'action-mismatch');
        }
        $score = (float)($r['score'] ?? 0);
        if ($score < $this->minScore) {
            return new RecaptchaResult(false, $score, 'low-score');
        }
        return new RecaptchaResult(true, $score, null);
    }
}
