<?php

declare(strict_types=1);

namespace App\Application\Actions\Customer;

use App\Application\Actions\Action;
use App\Application\Security\JwtService;
use App\Infrastructure\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/customer/verify — scambia un magic link token valido con un JWT owner (30 giorni).
 */
final class VerifyAccessAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly JwtService $jwt,
        private readonly Logger $logger,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $token = $this->str($data, 'token', 128);

        if ($token === null || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $this->error($response, 'invalid_token', 'Link non valido o scaduto. Richiedi un nuovo accesso.', 401);
        }

        $stmt = $this->pdo->prepare(
            'SELECT ml.id AS link_id, u.id, u.agency_id, u.role, u.first_name, u.last_name, u.email, u.is_active
             FROM magic_links ml
             JOIN users u ON u.id = ml.user_id
             WHERE ml.token_hash = :hash AND ml.used_at IS NULL AND ml.expires_at > now()'
        );
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();

        if (!$row || !$row['is_active'] || $row['role'] !== 'owner') {
            return $this->error($response, 'invalid_token', 'Link non valido o scaduto. Richiedi un nuovo accesso.', 401);
        }

        // Single-use: marca come usato
        $upd = $this->pdo->prepare('UPDATE magic_links SET used_at = now(), updated_at = now() WHERE id = :id');
        $upd->execute(['id' => $row['link_id']]);

        $updUser = $this->pdo->prepare('UPDATE users SET last_login_at = now(), updated_at = now() WHERE id = :id');
        $updUser->execute(['id' => $row['id']]);

        $jwtToken = $this->jwt->issue([
            'uid' => (int) $row['id'],
            'role' => 'owner',
            'agency_id' => (int) $row['agency_id'],
        ]);

        $this->logger->info('Accesso owner via magic link', ['uid' => $row['id']]);

        return $this->json($response, [
            'token' => $jwtToken,
            'expires_in' => JwtService::ttlForRole('owner'),
            'user' => [
                'id' => (int) $row['id'],
                'role' => 'owner',
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
            ],
        ]);
    }
}
