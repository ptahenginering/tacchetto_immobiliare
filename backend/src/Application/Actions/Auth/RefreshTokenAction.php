<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Application\Security\JwtService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * POST /api/auth/refresh — riemette un JWT valido a partire da uno non scaduto.
 */
final class RefreshTokenAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly JwtService $jwt,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $this->error($response, 'unauthorized', 'Token mancante.', 401);
        }

        try {
            $payload = $this->jwt->verify($m[1]);
        } catch (Throwable) {
            return $this->error($response, 'unauthorized', 'Token non valido o scaduto.', 401);
        }

        // L'utente deve essere ancora attivo
        $stmt = $this->pdo->prepare('SELECT id, agency_id, role, is_active FROM users WHERE id = :id');
        $stmt->execute(['id' => (int) ($payload['uid'] ?? 0)]);
        $user = $stmt->fetch();

        if (!$user || !$user['is_active']) {
            return $this->error($response, 'unauthorized', 'Utente non attivo.', 401);
        }

        $token = $this->jwt->issue([
            'uid' => (int) $user['id'],
            'role' => $user['role'],
            'agency_id' => (int) $user['agency_id'],
        ]);

        return $this->json($response, [
            'token' => $token,
            'expires_in' => JwtService::ttlForRole($user['role']),
        ]);
    }
}
