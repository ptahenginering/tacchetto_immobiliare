<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Application\Security\JwtService;
use App\Application\Security\LoginRateLimiter;
use App\Infrastructure\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/admin/login — email+password → JWT (ruoli admin/agent).
 */
final class AdminLoginAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly JwtService $jwt,
        private readonly LoginRateLimiter $rateLimiter,
        private readonly Logger $logger,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $ip = $this->clientIp($request);

        if ($this->rateLimiter->isBlocked($ip)) {
            return $this->error($response, 'rate_limited', 'Troppi tentativi. Riprova tra 15 minuti.', 429);
        }

        $data = $this->body($request);
        $email = mb_strtolower($this->str($data, 'email') ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->rateLimiter->record($ip, $email ?: null, false);
            return $this->error($response, 'invalid_credentials', 'Credenziali non valide.', 401);
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, agency_id, role, first_name, last_name, email, password_hash, is_active
             FROM users
             WHERE email = :email AND role IN ('admin', 'agent')"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !$user['is_active'] || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            $this->rateLimiter->record($ip, $email, false);
            return $this->error($response, 'invalid_credentials', 'Credenziali non valide.', 401);
        }

        $this->rateLimiter->record($ip, $email, true);

        $upd = $this->pdo->prepare('UPDATE users SET last_login_at = now(), updated_at = now() WHERE id = :id');
        $upd->execute(['id' => $user['id']]);

        $token = $this->jwt->issue([
            'uid' => (int) $user['id'],
            'role' => $user['role'],
            'agency_id' => (int) $user['agency_id'],
        ]);

        $this->logger->info('Login admin riuscito', ['uid' => $user['id'], 'ip' => $ip]);

        return $this->json($response, [
            'token' => $token,
            'expires_in' => JwtService::ttlForRole($user['role']),
            'user' => [
                'id' => (int) $user['id'],
                'role' => $user['role'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
            ],
        ]);
    }
}
