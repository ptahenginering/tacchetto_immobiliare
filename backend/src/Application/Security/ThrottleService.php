<?php

declare(strict_types=1);

namespace App\Application\Security;

use PDO;

/**
 * Rate limiting generico per gli endpoint pubblici, per IP e per "bucket"
 * (es. leads, chatbot). Storage su tabella request_throttle.
 */
final class ThrottleService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Registra la richiesta e ritorna true se il limite è superato.
     */
    public function tooManyRequests(string $bucket, string $ip, int $max, int $windowMinutes): bool
    {
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM request_throttle
             WHERE bucket = :bucket AND ip = :ip
               AND requested_at > now() - make_interval(mins => :mins)'
        );
        $count->execute(['bucket' => $bucket, 'ip' => $ip, 'mins' => $windowMinutes]);

        if ((int) $count->fetchColumn() >= $max) {
            return true;
        }

        $ins = $this->pdo->prepare('INSERT INTO request_throttle (bucket, ip) VALUES (:bucket, :ip)');
        $ins->execute(['bucket' => $bucket, 'ip' => $ip]);

        return false;
    }

    /** Pulizia record più vecchi di 24h (chiamata dal cron). */
    public function purgeOld(): int
    {
        return $this->pdo->exec("DELETE FROM request_throttle WHERE requested_at < now() - interval '24 hours'") ?: 0;
    }
}
