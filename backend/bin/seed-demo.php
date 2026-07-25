<?php

declare(strict_types=1);

/**
 * Seed dati demo RT CASA LIVE (CLI) — vedi DemoSeeder per il contenuto.
 * Uso: php bin/seed-demo.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\DemoSeeder;

$envDir = __DIR__ . '/../config';
if (is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->load();
}

echo "== Seed dati demo ==\n";

try {
    $result = (new DemoSeeder(Connection::get()))->run();
} catch (Throwable $e) {
    fwrite(STDERR, "ERRORE: {$e->getMessage()}\n");
    exit(1);
}

echo $result['created'] ? "Dati demo creati ✔\n" : "Dati demo già presenti (magic link rigenerato)\n";
echo "\n--- Accessi demo ---\n";
echo "Gestionale (agente): {$result['agent_email']} / {$result['agent_password']}\n";
echo "Area cliente (proprietario): {$result['owner_email']}\n";
echo "Magic link (valido 7 giorni):\n  {$result['owner_magic_link']}\n";
