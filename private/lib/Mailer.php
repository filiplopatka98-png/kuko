<?php
declare(strict_types=1);
namespace Kuko;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer implements MailerInterface
{
    public function __construct(private array $cfg) {}

    public function send(string $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool
    {
        if (!class_exists(PHPMailer::class)) {
            error_log('[Mailer] PHPMailer not installed');
            return false;
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $this->cfg['host'];
            $mail->Port       = (int) $this->cfg['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->cfg['user'];
            $mail->Password   = $this->cfg['pass'];
            $mail->SMTPSecure = $this->cfg['encryption'];
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($this->cfg['from_email'], $this->cfg['from_name']);
            if ($replyTo !== null) $mail->addReplyTo($replyTo);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;
            return $mail->send();
        } catch (\Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        }
    }
}
