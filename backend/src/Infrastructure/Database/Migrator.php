<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Applica le migrazioni SQL in ordine e registra le versioni in schema_migrations.
 * Usato sia dal CLI (bin/migrate.php) sia dall'endpoint remoto di deploy.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsDir,
    ) {
    }

    /**
     * @return string[] elenco delle migrazioni applicate in questa esecuzione
     */
    public function run(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) PRIMARY KEY,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )'
        );

        $applied = $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_flip($applied);

        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $ran = [];
        foreach ($files as $file) {
            $version = basename($file);
            if (isset($applied[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }

            try {
                $this->pdo->beginTransaction();
                $this->pdo->exec($sql);
                $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:v)');
                $stmt->execute(['v' => $version]);
                $this->pdo->commit();
                $ran[] = $version;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw new RuntimeException("Migrazione {$version} fallita: {$e->getMessage()}", 0, $e);
            }
        }

        return $ran;
    }

    /**
     * Crea (o riattiva) l'utente admin iniziale se non esiste.
     * Password da env ADMIN_DEFAULT_PASSWORD, hash bcrypt.
     *
     * @param bool $resetPassword se true forza il reset della password all'admin esistente
     * @return string 'creato' | 'esistente' | 'password_reimpostata' | 'saltato_senza_password'
     */
    public function ensureAdminUser(bool $resetPassword = false): string
    {
        $email = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: 'admin@rtimmobiliare.it';
        $password = $_ENV['ADMIN_DEFAULT_PASSWORD'] ?? getenv('ADMIN_DEFAULT_PASSWORD') ?: '';

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($resetPassword && $password !== '') {
                $upd = $this->pdo->prepare(
                    'UPDATE users SET password_hash = :hash, is_active = true, updated_at = now() WHERE id = :id'
                );
                $upd->execute([
                    'hash' => password_hash($password, PASSWORD_BCRYPT),
                    'id' => $existing['id'],
                ]);
                return 'password_reimpostata';
            }
            return 'esistente';
        }

        if ($password === '') {
            // Nessuna password configurata: non creiamo un admin con credenziale vuota.
            return 'saltato_senza_password';
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO users (agency_id, role, first_name, last_name, email, password_hash, is_active)
             VALUES (1, :role, :fn, :ln, :email, :hash, true)'
        );
        $ins->execute([
            'role' => 'admin',
            'fn' => 'Roberto',
            'ln' => 'Tacchetto',
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);

        return 'creato';
    }
}
