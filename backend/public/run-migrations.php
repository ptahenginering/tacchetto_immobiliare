<?php

declare(strict_types=1);

/**
 * Endpoint migrazioni remote (post-deploy SiteGround).
 *
 *   GET /api/public/run-migrations.php?key=MIGRATION_KEY[&reset-admin=1]
 *
 * Protetto dalla chiave segreta MIGRATION_KEY (env). Idempotente.
 * Oltre alle migrazioni: assicura l'utente admin e blinda le cartelle upload.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\Migrator;

header('Content-Type: application/json; charset=utf-8');

$envDir = __DIR__ . '/../config';
if (is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$expectedKey = $_ENV['MIGRATION_KEY'] ?? '';
$providedKey = $_GET['key'] ?? '';

if ($expectedKey === '' || !hash_equals($expectedKey, (string) $providedKey)) {
    http_response_code(403);
    echo json_encode(['error' => ['code' => 'forbidden', 'message' => 'Chiave non valida.']]);
    exit;
}

try {
    $pdo = Connection::get();
    $migrator = new Migrator($pdo, __DIR__ . '/../migrations');

    $ran = $migrator->run();
    $admin = $migrator->ensureAdminUser(isset($_GET['reset-admin']));

    // Blinda le cartelle upload: niente esecuzione PHP, niente listing
    $uploadDirs = [
        __DIR__ . '/../storage/uploads',
        __DIR__ . '/../storage/uploads/properties',
    ];
    $htaccess = <<<HT
<FilesMatch "\\.(php|php3|php4|php5|php7|phtml|phar|pl|py|rb|sh|cgi|asp|aspx|jsp)$">
    Require all denied
</FilesMatch>
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
Options -ExecCGI -Indexes
HT;
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/.htaccess', $htaccess);
    }

    echo json_encode([
        'ok' => true,
        'migrations_applied' => $ran,
        'admin_user' => $admin,
        'time' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => ['code' => 'migration_failed', 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
}
