<?php

declare(strict_types=1);

namespace App\Application\Actions\Customer;

use App\Application\Actions\Action;
use App\Application\Security\LoginRateLimiter;
use App\Domain\Mail\MailerInterface;
use App\Infrastructure\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/customer/request-access — richiesta magic link via email.
 *
 * Risposta SEMPRE identica, che l'email esista o no (no user enumeration).
 * Token valido 30 minuti, salvato come hash SHA-256.
 */
final class RequestAccessAction extends Action
{
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private readonly PDO $pdo,
        private readonly MailerInterface $mailer,
        private readonly LoginRateLimiter $rateLimiter,
        private readonly Logger $logger,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $ip = $this->clientIp($request);
        if ($this->rateLimiter->isBlocked($ip)) {
            return $this->error($response, 'rate_limited', 'Troppi tentativi. Riprova tra qualche minuto.', 429);
        }

        $data = $this->body($request);
        $email = mb_strtolower($this->str($data, 'email') ?? '');

        $genericReply = [
            'message' => 'Se l\'indirizzo è registrato, ti abbiamo inviato un link di accesso. Controlla la posta (anche lo spam).',
        ];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Nessun dettaglio: risposta identica
            $this->rateLimiter->record($ip, null, false);
            return $this->json($response, $genericReply);
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, first_name, email FROM users WHERE email = :email AND role = 'owner' AND is_active = true"
        );
        $stmt->execute(['email' => $email]);
        $owner = $stmt->fetch();

        if ($owner) {
            $token = bin2hex(random_bytes(32));

            $ins = $this->pdo->prepare(
                'INSERT INTO magic_links (agency_id, user_id, token_hash, expires_at)
                 VALUES (1, :uid, :hash, now() + make_interval(mins => :mins))'
            );
            $ins->execute([
                'uid' => $owner['id'],
                'hash' => hash('sha256', $token),
                'mins' => self::TOKEN_TTL_MINUTES,
            ]);

            $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://tacchettoimmobiliare.it', '/');
            $link = $appUrl . '/app/access?token=' . $token;

            $this->mailer->send($owner['email'], 'magic_link', [
                'first_name' => $owner['first_name'],
                'link' => $link,
                'minutes' => self::TOKEN_TTL_MINUTES,
            ], 'user', (int) $owner['id']);

            $this->logger->info('Magic link generato', ['uid' => $owner['id']]);
        } else {
            // registra comunque il tentativo per il rate limit
            $this->rateLimiter->record($ip, $email, false);
        }

        return $this->json($response, $genericReply);
    }
}
