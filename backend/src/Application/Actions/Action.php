<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Base class per tutte le Action: helper JSON e lettura body.
 */
abstract class Action
{
    protected function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    protected function error(Response $response, string $code, string $message, int $status): Response
    {
        return $this->json($response, ['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /** @return array<string, mixed> */
    protected function body(Request $request): array
    {
        $parsed = $request->getParsedBody();
        return is_array($parsed) ? $parsed : [];
    }

    protected function clientIp(Request $request): string
    {
        $server = $request->getServerParams();
        // Dietro proxy/CDN (SiteGround) l'IP reale è nel primo X-Forwarded-For
        $xff = $request->getHeaderLine('X-Forwarded-For');
        if ($xff !== '') {
            $parts = explode(',', $xff);
            return trim($parts[0]);
        }
        return $server['REMOTE_ADDR'] ?? 'unknown';
    }

    /** Stringa trim con lunghezza massima, null se vuota. */
    protected function str(array $data, string $key, int $maxLen = 255): ?string
    {
        $v = $data[$key] ?? null;
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        return mb_substr($v, 0, $maxLen);
    }
}
