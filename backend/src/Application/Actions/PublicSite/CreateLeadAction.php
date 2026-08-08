<?php

declare(strict_types=1);

namespace App\Application\Actions\PublicSite;

use App\Application\Actions\Action;
use App\Application\Security\ThrottleService;
use App\Domain\Mail\MailerInterface;
use App\Infrastructure\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/public/leads — riceve i lead dal form del sito vetrina.
 *
 * Protezioni: honeypot "website" (se compilato → 200 finto e scarta),
 * rate limit per IP, validazione server-side.
 */
final class CreateLeadAction extends Action
{
    private const ALLOWED_REQUEST_TYPES = ['vendere', 'ereditato', 'perizia', 'altro'];
    private const ALLOWED_SOURCES = ['sito', 'qr', 'social', 'referral', 'altro'];
    private const MAX_PER_IP = 5;          // massimo 5 invii
    private const WINDOW_MINUTES = 60;     // per ora

    public function __construct(
        private readonly PDO $pdo,
        private readonly MailerInterface $mailer,
        private readonly ThrottleService $throttle,
        private readonly Logger $logger,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $this->body($request);

        // Honeypot: i bot compilano tutto. Risposta identica al successo, ma si scarta.
        if (($data['website'] ?? '') !== '') {
            $this->logger->warning('Lead scartato: honeypot compilato', ['ip' => $this->clientIp($request)]);
            return $this->json($response, ['ok' => true, 'message' => 'Grazie! Ti ricontatteremo al più presto.']);
        }

        $ip = $this->clientIp($request);
        if ($this->throttle->tooManyRequests('leads', $ip, self::MAX_PER_IP, self::WINDOW_MINUTES)) {
            return $this->error($response, 'rate_limited', 'Hai già inviato diverse richieste. Riprova più tardi.', 429);
        }

        // --- Validazione ---
        $firstName = $this->str($data, 'first_name', 100);
        $lastName = $this->str($data, 'last_name', 100);
        $email = mb_strtolower($this->str($data, 'email') ?? '');
        $phone = $this->str($data, 'phone', 50);
        $message = $this->str($data, 'message', 3000);

        $requestType = $this->str($data, 'request_type', 20) ?? 'vendere';
        if (!in_array($requestType, self::ALLOWED_REQUEST_TYPES, true)) {
            $requestType = 'altro';
        }

        $source = $this->str($data, 'source', 20) ?? 'sito';
        if (!in_array($source, self::ALLOWED_SOURCES, true)) {
            $source = 'sito';
        }

        $errors = [];
        if ($firstName === null) {
            $errors['first_name'] = 'Il nome è obbligatorio.';
        }
        if ($lastName === null) {
            $errors['last_name'] = 'Il cognome è obbligatorio.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email non valida.';
        }
        if ($email === '' && $phone === null) {
            $errors['contact'] = 'Serve almeno un recapito: email o telefono.';
        }

        if ($errors !== []) {
            return $this->json($response, [
                'error' => ['code' => 'validation', 'message' => 'Controlla i campi indicati.', 'fields' => $errors],
            ], 422);
        }

        // --- Salvataggio ---
        $stmt = $this->pdo->prepare(
            'INSERT INTO leads (agency_id, first_name, last_name, email, phone, request_type, message, source, status)
             VALUES (1, :first_name, :last_name, :email, :phone, :request_type, :message, :source, \'nuovo\')
             RETURNING id'
        );
        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'request_type' => $requestType,
            'message' => $message,
            'source' => $source,
        ]);
        $leadId = (int) $stmt->fetchColumn();

        $this->logger->info('Nuovo lead ricevuto', ['id' => $leadId, 'source' => $source]);

        // --- Notifica a Roberto ---
        $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@rtimmobiliare.it';
        $this->mailer->send($adminEmail, 'nuovo_lead_admin', [
            'lead_name' => trim("{$firstName} {$lastName}"),
            'source' => $source,
            'request_type' => $requestType,
        ], 'lead', $leadId);

        // --- Autoresponder opzionale al lead ---
        $autoresponder = filter_var($_ENV['LEAD_AUTORESPONDER'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if ($autoresponder && $email !== '') {
            $this->mailer->send($email, 'lead_autoresponder', [
                'first_name' => $firstName,
            ], 'lead', $leadId);
        }

        return $this->json($response, ['ok' => true, 'message' => 'Grazie! Ti ricontatteremo al più presto.'], 201);
    }
}
