<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\System;

use App\Application\Actions\Action;
use App\Domain\Mail\MailerInterface;
use App\Infrastructure\Ai\AnthropicService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Sistema e impostazioni:
 *   GET  /api/admin/system/health  — stato integrazioni (Brevo/Anthropic/SMTP/DB)
 *   GET  /api/admin/settings       — dati agenzia
 *   PUT  /api/admin/settings       — aggiorna dati agenzia
 *   POST /api/admin/system/test-email — invia una email di prova
 */
final class SystemAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MailerInterface $mailer,
        private readonly AnthropicService $anthropic,
    ) {
    }

    public function health(Request $request, Response $response): Response
    {
        $brevoConfigured = ($_ENV['BREVO_API_KEY'] ?? '') !== '';
        $smtpConfigured = ($_ENV['SMTP_HOST'] ?? '') !== '';

        return $this->json($response, [
            'data' => [
                'database' => true, // se rispondiamo, il DB c'è
                'brevo' => $brevoConfigured,
                'smtp_fallback' => $smtpConfigured,
                'email_delivery' => $brevoConfigured || $smtpConfigured,
                'anthropic' => $this->anthropic->isConfigured(),
                'anthropic_model' => $this->anthropic->isConfigured() ? $this->anthropic->model() : null,
                'app_env' => $_ENV['APP_ENV'] ?? 'production',
            ],
        ]);
    }

    public function getSettings(Request $request, Response $response): Response
    {
        $agency = $this->pdo->query('SELECT id, name, email, phone, logo_url, primary_color, settings FROM agencies WHERE id = 1')->fetch();
        $agency['settings'] = json_decode((string) $agency['settings'], true) ?: (object) [];
        return $this->json($response, ['data' => $agency]);
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        $data = $this->body($request);

        $sets = [];
        $params = [];
        foreach (['name' => 255, 'email' => 255, 'phone' => 50, 'logo_url' => 500, 'primary_color' => 20] as $field => $maxLen) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $this->str($data, $field, $maxLen);
            }
        }
        if (isset($data['settings']) && is_array($data['settings'])) {
            $sets[] = 'settings = (:settings)::jsonb';
            $params['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE agencies SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);

        return $this->getSettings($request, $response);
    }

    public function testEmail(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $to = mb_strtolower($this->str($data, 'to') ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->error($response, 'validation', 'Indirizzo email di destinazione non valido.', 422);
        }

        // Riusa il template magic_link come email di prova brandizzata
        $sent = $this->mailer->send($to, 'magic_link', [
            'first_name' => 'Test',
            'link' => ($_ENV['APP_URL'] ?? 'https://tacchettoimmobiliare.it') . '/app/',
            'minutes' => 30,
        ], 'test', null);

        return $this->json($response, [
            'data' => [
                'sent' => $sent,
                'message' => $sent
                    ? "Email di prova inviata a {$to}."
                    : 'Invio non riuscito o provider non configurato: controlla email_log e le chiavi BREVO_API_KEY/SMTP_*.',
            ],
        ]);
    }
}
