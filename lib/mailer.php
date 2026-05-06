<?php
declare(strict_types=1);

namespace Nightingale;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Thin wrapper around PHPMailer.  When `MAIL_LOG_ONLY=true` (the
 * default in `.env.example`), every message is appended to
 * storage/mail.log instead of being sent — useful for development
 * without a real SMTP account.
 */
final class Mailer
{
    public static function send(
        string $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        array $attachments = []
    ): bool {
        $logOnly = strtolower((string) ($_ENV['MAIL_LOG_ONLY'] ?? 'true')) === 'true';

        if ($logOnly) {
            return self::logOnly($to, $subject, $bodyHtml, $attachments);
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'] ?? 'localhost';
            $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? 587);
            $mail->Username   = $_ENV['SMTP_USER']     ?? '';
            $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
            $mail->SMTPAuth   = $mail->Username !== '';
            $encryption       = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(
                $_ENV['SMTP_FROM']      ?? 'no-reply@nightingale.clinic',
                $_ENV['SMTP_FROM_NAME'] ?? 'Nightingale Clinic'
            );
            $mail->addAddress($to);

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText ?? strip_tags($bodyHtml);

            foreach ($attachments as $att) {
                $mail->addStringAttachment(
                    $att['content'],
                    $att['filename'] ?? 'attachment',
                    PHPMailer::ENCODING_BASE64,
                    $att['type'] ?? 'application/octet-stream'
                );
            }

            return $mail->send();
        } catch (MailException $e) {
            error_log('[Nightingale\\Mailer] ' . $e->getMessage());
            return false;
        }
    }

    private static function logOnly(string $to, string $subject, string $body, array $attachments): bool
    {
        $dir = NIGHTINGALE_ROOT . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $line  = "═════════════════════════════════════════════════════════════\n";
        $line .= '[' . date('Y-m-d H:i:s') . "] To: $to\nSubject: $subject\n\n";
        $line .= $body . "\n";
        if (!empty($attachments)) {
            $line .= "\n[Attachments]\n";
            foreach ($attachments as $att) {
                $size = strlen($att['content'] ?? '');
                $line .= sprintf("  - %s (%s, %d bytes)\n",
                    $att['filename'] ?? 'attachment',
                    $att['type']     ?? 'application/octet-stream',
                    $size
                );
            }
        }
        @file_put_contents("$dir/mail.log", $line, FILE_APPEND);
        return true;
    }
}
