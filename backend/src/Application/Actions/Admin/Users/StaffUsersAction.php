<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Users;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Gestione utenti staff (solo ruolo admin può creare/modificare):
 *   GET  /api/admin/users            — staff + proprietari
 *   POST /api/admin/users            — crea utente staff
 *   PUT  /api/admin/users/{id}       — aggiorna staff (attiva/disattiva, password)
 */
final class StaffUsersAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        $role = $request->getQueryParams()['role'] ?? '';

        $sql = 'SELECT id, role, first_name, last_name, email, phone, is_active, last_login_at, created_at
                FROM users WHERE agency_id = 1';
        $params = [];
        if (in_array($role, ['admin', 'agent', 'owner'], true)) {
            $sql .= ' AND role = :role';
            $params['role'] = $role;
        }
        $sql .= " ORDER BY CASE role WHEN 'admin' THEN 0 WHEN 'agent' THEN 1 ELSE 2 END, created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }

    public function save(Request $request, Response $response, array $args): Response
    {
        // Solo gli admin gestiscono lo staff
        if ($request->getAttribute('auth_role') !== 'admin') {
            return $this->error($response, 'forbidden', 'Solo un admin può gestire gli utenti.', 403);
        }

        $id = isset($args['id']) ? (int) $args['id'] : null;
        $data = $this->body($request);

        if ($id === null) {
            $email = mb_strtolower($this->str($data, 'email') ?? '');
            $firstName = $this->str($data, 'first_name', 100);
            $lastName = $this->str($data, 'last_name', 100);
            $password = (string) ($data['password'] ?? '');
            $role = $this->str($data, 'role', 20) ?? 'agent';

            if (!in_array($role, ['admin', 'agent'], true)) {
                return $this->error($response, 'validation', 'Ruolo non valido (admin o agent).', 422);
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === null || $lastName === null) {
                return $this->error($response, 'validation', 'Nome, cognome ed email validi obbligatori.', 422);
            }
            if (strlen($password) < 8) {
                return $this->error($response, 'validation', 'Password di almeno 8 caratteri.', 422);
            }

            $exists = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
            $exists->execute(['email' => $email]);
            if ($exists->fetch()) {
                return $this->error($response, 'validation', 'Esiste già un utente con questa email.', 422);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO users (agency_id, role, first_name, last_name, email, phone, password_hash, is_active)
                 VALUES (1, :role, :fn, :ln, :email, :phone, :hash, true) RETURNING id'
            );
            $stmt->execute([
                'role' => $role,
                'fn' => $firstName,
                'ln' => $lastName,
                'email' => $email,
                'phone' => $this->str($data, 'phone', 50),
                'hash' => password_hash($password, PASSWORD_BCRYPT),
            ]);

            return $this->json($response, ['data' => ['id' => (int) $stmt->fetchColumn()]], 201);
        }

        $target = $this->pdo->prepare("SELECT id, role FROM users WHERE id = :id AND agency_id = 1 AND role IN ('admin','agent')");
        $target->execute(['id' => $id]);
        if (!$target->fetch()) {
            return $this->error($response, 'not_found', 'Utente staff non trovato.', 404);
        }

        $sets = [];
        $params = ['id' => $id];

        foreach (['first_name' => 100, 'last_name' => 100, 'phone' => 50] as $field => $maxLen) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $this->str($data, $field, $maxLen);
            }
        }
        if (array_key_exists('is_active', $data)) {
            // Un admin non può disattivare sé stesso
            if ((int) $request->getAttribute('auth_uid') === $id && !filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)) {
                return $this->error($response, 'validation', 'Non puoi disattivare il tuo stesso account.', 422);
            }
            $sets[] = 'is_active = :is_active';
            $params['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        if (!empty($data['password'])) {
            if (strlen((string) $data['password']) < 8) {
                return $this->error($response, 'validation', 'Password di almeno 8 caratteri.', 422);
            }
            $sets[] = 'password_hash = :hash';
            $params['hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $this->json($response, ['ok' => true]);
    }
}
