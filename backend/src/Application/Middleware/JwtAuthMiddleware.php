<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Security\JwtService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Throwable;

/**
 * Middleware JWT base: verifica il Bearer token e controlla che il ruolo sia
 * tra quelli ammessi. Attacca al request: auth_uid, auth_role, auth_agency_id.
 */
abstract class JwtAuthMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $allowedRoles = [];

    public function __construct(protected readonly JwtService $jwt)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $this->unauthorized('Token mancante.');
        }

        try {
            $payload = $this->jwt->verify($m[1]);
        } catch (Throwable) {
            return $this->unauthorized('Token non valido o scaduto.');
        }

        $role = (string) ($payload['role'] ?? '');
        if (!in_array($role, $this->allowedRoles, true)) {
            return $this->forbidden();
        }

        $request = $request
            ->withAttribute('auth_uid', (int) ($payload['uid'] ?? 0))
            ->withAttribute('auth_role', $role)
            ->withAttribute('auth_agency_id', (int) ($payload['agency_id'] ?? 0));

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse(401);
        $response->getBody()->write(json_encode(['error' => ['code' => 'unauthorized', 'message' => $message]], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function forbidden(): Response
    {
        $response = new SlimResponse(403);
        $response->getBody()->write(json_encode(['error' => ['code' => 'forbidden', 'message' => 'Permessi insufficienti.']], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
