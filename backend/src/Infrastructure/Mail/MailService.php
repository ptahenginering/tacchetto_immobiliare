<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\MailerInterface;
use App\Infrastructure\Logger;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Servizio email con cascata di delivery:
 *   1. Brevo API (se BREVO_API_KEY presente)
 *   2. SMTP via PHPMailer (se SMTP_HOST presente)
 *   3. Nessun provider → registra in email_log con status "disattivata"
 * Ogni invio (riuscito o no) è registrato in email_log. Non lancia mai:
 * un problema email non deve rompere il flusso applicativo.
 */
final class MailService implements MailerInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly EmailTemplates $templates,
        private readonly Logger $logger,
        private readonly ?BrevoService $brevo = null,
    ) {
    }

    public function send(
        string $toEmail,
        string $templateKey,
        array $vars = [],
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): bool {
        try {
            $rendered = $this->templates->render($templateKey, $vars);
        } catch (Throwable $e) {
            $this->logger->error('Template email non valido', ['template' => $templateKey, 'error' => $e->getMessage()]);
            return false;
        }

        $subject = $rendered['subject'];
        $html = $rendered['html'];

        // 1. Brevo
        if ($this->brevo !== null && $this->brevo->isConfigured()) {
            try {
                $this->brevo->sendEmail($toEmail, $subject, $html);
                $this->log($toEmail, $templateKey, $subject, 'inviata', null, $relatedType, $relatedId);
                return true;
            } catch (Throwable $e) {
                $this->logger->warning('Brevo fallito, provo SMTP', ['error' => $e->getMessage()]);
                // continua con SMTP
            }
        }

        // 2. SMTP fallback
        $smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '';
        if ($smtpHost !== '') {
            try {
                $this->sendViaSmtp($toEmail, $subject, $html, $smtpHost);
                $this->log($toEmail, $templateKey, $subject, 'inviata', null, $relatedType, $relatedId);
                return true;
            } catch (Throwable $e) {
                $this->logger->error('Invio SMTP fallito', ['to' => $toEmail, 'error' => $e->getMessage()]);
                $this->log($toEmail, $templateKey, $subject, 'errore', $e->getMessage(), $relatedType, $relatedId);
                return false;
            }
        }

        // 3. Nessun provider configurato
        $this->logger->warning('Email non inviata: nessun provider configurato', ['template' => $templateKey, 'to' => $toEmail]);
        $this->log(
            $toEmail,
            $templateKey,
            $subject,
            'disattivata',
            'Nessun provider email configurato (BREVO_API_KEY o SMTP_* mancanti).',
            $relatedType,
            $relatedId
        );

        return false;
    }

    private function sendViaSmtp(string $toEmail, string $subject, string $html, string $smtpHost): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = (int) ($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
        $mail->Password = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $fromEmail = $_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?: 'info@rtimmobiliare.it';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'RT CASA LIVE';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html) ?? '');

        $mail->send();
    }

    private function log(
        string $toEmail,
        string $templateKey,
        string $subject,
        string $status,
        ?string $errorText,
        ?string $relatedType,
        ?int $relatedId,
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO email_log (agency_id, to_email, template_key, subject, status, error_text, related_type, related_id)
                 VALUES (1, :to_email, :template_key, :subject, :status, :error_text, :related_type, :related_id)'
            );
            $stmt->execute([
                'to_email' => $toEmail,
                'template_key' => $templateKey,
                'subject' => $subject,
                'status' => $status,
                'error_text' => $errorText,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Impossibile scrivere email_log', ['error' => $e->getMessage()]);
        }
    }
}
