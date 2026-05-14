<?php
declare(strict_types=1);
namespace Kuko;

final class FakeMailer implements MailerInterface
{
    /** @var array<int,array<string,string|null>> */
    public array $sent = [];
    public bool $shouldFail = false;

    public function send(string $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool
    {
        if ($this->shouldFail) return false;
        $this->sent[] = compact('to', 'subject', 'htmlBody', 'textBody', 'replyTo');
        return true;
    }
}
