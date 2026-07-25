<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Logger minimale su file (backend/logs/app.log).
 * Formato: [2026-07-25 10:00:00] LEVEL: messaggio {contesto json}
 */
final class Logger
{
    public function __construct(private readonly string $logFile)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
