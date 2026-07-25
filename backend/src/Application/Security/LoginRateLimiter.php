<?php

declare(strict_types=1);

namespace App\Application\Security;

use PDO;

/**
 * Rate limiting sui login: massimo N tentativi falliti per IP in una finestra
 * di tempo. Storage su tabella login_attempts.
 */
final class LoginRateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxAttempts = 10,
        private readonly int $windowMinutes = 15,
    ) {
    }

    public function isBlocked(string $ip): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip = :ip AND succeeded = false
               AND attempted_at > now() - make_interval(mins => :mins)"
        );
        $stmt->execute(['ip' => $ip, 'mins' => $this->windowMinutes]);

        return (int) $stmt->fetchColumn() >= $this->maxAttempts;
    }

    public function record(string $ip, ?string $email, bool $succeeded): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip, email, succeeded) VALUES (:ip, :email, :ok)'
        );
        $stmt->execute(['ip' => $ip, 'email' => $email, 'ok' => $succeeded ? 'true' : 'false']);
    }

    /** Pulizia tentativi più vecchi di 24h (chiamata dal cron). */
    public function purgeOld(): int
    {
        return $this->pdo->exec("DELETE FROM login_attempts WHERE attempted_at < now() - interval '24 hours'") ?: 0;
    }
}
