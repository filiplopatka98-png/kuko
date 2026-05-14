<?php
declare(strict_types=1);
namespace Kuko;

interface MailerInterface
{
    public function send(string $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool;
}
