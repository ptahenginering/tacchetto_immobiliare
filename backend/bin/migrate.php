<?php

declare(strict_types=1);

/**
 * Migration runner RT CASA LIVE.
 *
 * Esegue in ordine alfabetico i file backend/migrations/NNN_*.sql non ancora
 * applicati e li registra in schema_migrations. Idempotente: rilanciarlo è
 * sempre sicuro.
 *
 * Uso: php bin/migrate.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\Connection;

// Carica env da config/.env se presente (in produzione le env possono già esserci)
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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )'
);

$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files, SORT_STRING);

$ran = 0;
foreach ($files as $file) {
    $version = basename($file);
    if (isset($applied[$version])) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "  ! {$version}: file vuoto o illeggibile, salto\n");
        continue;
    }

    echo "  → {$version}... ";
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:v)');
        $stmt->execute(['v' => $version]);
        $pdo->commit();
        echo "OK\n";
        $ran++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "ERRORE\n  {$e->getMessage()}\n");
        exit(1);
    }
}

echo $ran === 0 ? "Nessuna migrazione da applicare, schema aggiornato.\n" : "Applicate {$ran} migrazioni.\n";
