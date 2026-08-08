<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use RuntimeException;

/**
 * Client minimale API Brevo v3 (email transazionali).
 * https://developers.brevo.com/reference/sendtransacemail
 */
final class BrevoService
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param array<int, array{name: string, content: string}> $attachments allegati (contenuto binario)
     * @throws RuntimeException se l'invio fallisce
     */
    public function sendEmail(string $toEmail, string $subject, string $html, array $attachments = []): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('BREVO_API_KEY non configurata.');
        }

        $body = [
            'sender' => ['email' => $this->fromEmail, 'name' => $this->fromName],
            'to' => [['email' => $toEmail]],
            'subject' => $subject,
            'htmlContent' => $html,
        ];
        if ($attachments !== []) {
            $body['attachment'] = array_map(
                fn (array $a) => ['name' => $a['name'], 'content' => base64_encode($a['content'])],
                $attachments
            );
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $this->apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Brevo: errore di rete ({$curlErr})");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Brevo: HTTP {$status} — " . substr((string) $body, 0, 500));
        }
    }
}
