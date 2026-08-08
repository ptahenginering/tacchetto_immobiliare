<?php

declare(strict_types=1);

namespace App\Domain\Mail;

/**
 * Astrazione invio email transazionali.
 * Implementazioni: BrevoService (API), fallback SMTP PHPMailer, log-only.
 */
interface MailerInterface
{
    /**
     * Invia una email basata su template.
     *
     * @param string $toEmail destinatario
     * @param string $templateKey chiave template (magic_link, nuovo_lead_admin, ...)
     * @param array<string, mixed> $vars variabili per il template
     * @param string|null $relatedType entità collegata (lead, visit, proposal, ...)
     * @param int|null $relatedId id entità collegata
     * @param array<int, array{name: string, content: string}> $attachments allegati (contenuto binario)
     * @return bool true se inviata (o accettata dal provider)
     */
    public function send(
        string $toEmail,
        string $templateKey,
        array $vars = [],
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $attachments = [],
    ): bool;
}
