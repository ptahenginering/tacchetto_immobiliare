<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Leads;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PUT /api/admin/leads/{id} — aggiorna status, assegnazione, note, dati contatto.
 */
final class UpdateLeadAction extends Action
{
    private const ALLOWED_STATUS = ['nuovo', 'contattato', 'appuntamento', 'incarico', 'perso'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $data = $this->body($request);

        $exists = $this->pdo->prepare('SELECT id FROM leads WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Lead non trovato.', 404);
        }

        $sets = [];
        $params = ['id' => $id];

        if (array_key_exists('status', $data)) {
            if (!in_array($data['status'], self::ALLOWED_STATUS, true)) {
                return $this->error($response, 'validation', 'Status non valido.', 422);
            }
            $sets[] = 'status = :status';
            $params['status'] = $data['status'];
        }

        if (array_key_exists('assigned_to', $data)) {
            if ($data['assigned_to'] === null || $data['assigned_to'] === '') {
                $sets[] = 'assigned_to = NULL';
            } else {
                $check = $this->pdo->prepare("SELECT id FROM users WHERE id = :id AND role IN ('admin','agent') AND is_active = true");
                $check->execute(['id' => (int) $data['assigned_to']]);
                if (!$check->fetch()) {
                    return $this->error($response, 'validation', 'Utente assegnatario non valido.', 422);
                }
                $sets[] = 'assigned_to = :assigned_to';
                $params['assigned_to'] = (int) $data['assigned_to'];
            }
        }

        foreach (['notes' => 5000, 'lost_reason' => 1000, 'first_name' => 100, 'last_name' => 100, 'phone' => 50] as $field => $maxLen) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $this->str($data, $field, $maxLen);
            }
        }

        if (array_key_exists('email', $data)) {
            $email = mb_strtolower($this->str($data, 'email') ?? '');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->error($response, 'validation', 'Email non valida.', 422);
            }
            $sets[] = 'email = :email';
            $params['email'] = $email !== '' ? $email : null;
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $sql = 'UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);

        $stmt = $this->pdo->prepare('SELECT * FROM leads WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $this->json($response, ['data' => $stmt->fetch()]);
    }
}
