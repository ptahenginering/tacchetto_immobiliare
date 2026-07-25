<?php

declare(strict_types=1);

/**
 * Migration runner RT CASA LIVE (CLI).
 *
 * Esegue le migrazioni in backend/migrations/ e assicura l'utente admin iniziale.
 * Idempotente: rilanciarlo è sempre sicuro.
 *
 * Uso: php bin/migrate.php [--reset-admin-password]
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\Migrator;

$envDir = __DIR__ . '/../config';
if (is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->load();
}

echo "== RT CASA LIVE — migrazioni ==\n";

try {
    $pdo = Connection::get();
} catch (Throwable $e) {
    fwrite(STDERR, "Connessione fallita: {$e->getMessage()}\n");
    exit(1);
}

$migrator = new Migrator($pdo, __DIR__ . '/../migrations');

try {
    $ran = $migrator->run();
} catch (Throwable $e) {
    fwrite(STDERR, "ERRORE: {$e->getMessage()}\n");
    exit(1);
}

foreach ($ran as $version) {
    echo "  → {$version} OK\n";
}
echo $ran === [] ? "Nessuna migrazione da applicare, schema aggiornato.\n" : 'Applicate ' . count($ran) . " migrazioni.\n";

$resetPwd = in_array('--reset-admin-password', $argv, true);
$adminResult = $migrator->ensureAdminUser($resetPwd);
echo "Utente admin: {$adminResult}\n";
