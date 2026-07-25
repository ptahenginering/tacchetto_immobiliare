<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * CORS configurabile via env CORS_ALLOWED_ORIGINS (lista separata da virgole).
 * Risponde alle preflight OPTIONS e aggiunge gli header solo per origin ammesse.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowedOrigins;

    public function __construct(string $allowedOrigins)
    {
        $this->allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOrigins))));
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');

        if ($request->getMethod() === 'OPTIONS') {
            $response = new SlimResponse(204);
        } else {
            $response = $handler->handle($request);
        }

        if ($origin !== '' && $this->isAllowed($origin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Vary', 'Origin')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->withHeader('Access-Control-Max-Age', '86400');
        }

        return $response;
    }

    private function isAllowed(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }
}
