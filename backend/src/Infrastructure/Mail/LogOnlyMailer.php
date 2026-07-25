<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\MailerInterface;
use PDO;

/**
 * Mailer di ripiego: non invia nulla, registra soltanto in email_log con
 * status "disattivata". Usato quando nessun provider email è configurato.
 */
final class LogOnlyMailer implements MailerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function send(
        string $toEmail,
        string $templateKey,
        array $vars = [],
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): bool {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_log (agency_id, to_email, template_key, subject, status, error_text, related_type, related_id)
             VALUES (1, :to_email, :template_key, :subject, :status, :error_text, :related_type, :related_id)'
        );
        $stmt->execute([
            'to_email' => $toEmail,
            'template_key' => $templateKey,
            'subject' => '[non inviata] ' . $templateKey,
            'status' => 'disattivata',
            'error_text' => 'Nessun provider email configurato (BREVO_API_KEY o SMTP_* mancanti).',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        return false;
    }
}
