<?php

declare(strict_types=1);

namespace App\Application\Services;

use PDO;

/**
 * Generazione magic link per l'accesso dei proprietari.
 * Il token viaggia in chiaro nel link, a DB si salva solo l'hash SHA-256.
 */
final class MagicLinkService
{
    public const TOKEN_TTL_MINUTES = 30;
    public const WELCOME_TTL_MINUTES = 10080; // 7 giorni per il link di benvenuto

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Crea un magic link per l'utente e ritorna l'URL completo.
     */
    public function createForUser(int $userId, int $ttlMinutes = self::TOKEN_TTL_MINUTES): string
    {
        $token = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            'INSERT INTO magic_links (agency_id, user_id, token_hash, expires_at)
             VALUES (1, :uid, :hash, now() + make_interval(mins => :mins))'
        );
        $stmt->execute([
            'uid' => $userId,
            'hash' => hash('sha256', $token),
            'mins' => $ttlMinutes,
        ]);

        $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://tacchettoimmobiliare.it', '/');

        return $appUrl . '/app/access?token=' . $token;
    }

    /** Elimina i magic link scaduti da più di 7 giorni (chiamato dal cron). */
    public function purgeExpired(): int
    {
        return $this->pdo->exec(
            "DELETE FROM magic_links WHERE expires_at < now() - interval '7 days'"
        ) ?: 0;
    }
}
