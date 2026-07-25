<?php

declare(strict_types=1);

namespace App\Application\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/**
 * Emissione e verifica dei JWT.
 * Fail fast: JWT_SECRET obbligatorio (T38 hardening).
 */
final class JwtService
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $secret = $secret ?? $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';
        if ($secret === '' || strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET mancante o troppo corto (minimo 32 caratteri). Genera con: openssl rand -hex 32');
        }
        $this->secret = $secret;
    }

    public static function ttlForRole(string $role): int
    {
        if ($role === 'owner') {
            return (int) ($_ENV['JWT_TTL_OWNER'] ?? getenv('JWT_TTL_OWNER') ?: 2592000); // 30 giorni
        }
        return (int) ($_ENV['JWT_TTL_ADMIN'] ?? getenv('JWT_TTL_ADMIN') ?: 28800); // 8 ore
    }

    /**
     * @param array{uid:int, role:string, agency_id:int} $claims
     */
    public function issue(array $claims, ?int $ttl = null): string
    {
        $now = time();
        $ttl = $ttl ?? self::ttlForRole($claims['role']);

        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $ttl,
            'iss' => 'rt-casa-live',
        ]);

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * @return array<string, mixed> payload decodificato
     * @throws \Throwable se il token è invalido o scaduto
     */
    public function verify(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        return (array) $decoded;
    }
}
