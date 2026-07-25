<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Proposals;

use App\Application\Actions\Action;
use App\Domain\Mail\MailerInterface;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/admin/proposals (crea; se visibile → email nuova_proposta
 *      al proprietario, SENZA importo: invito ad aprire l'app)
 * PUT  /api/admin/proposals/{id} (aggiorna stato/note/importo)
 */
final class SaveProposalAction extends Action
{
    private const ALLOWED_STATUS = ['ricevuta', 'in_trattativa', 'accettata', 'rifiutata', 'ritirata'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $data = $this->body($request);

        if ($id === null) {
            return $this->create($response, $data);
        }
        return $this->update($response, $id, $data);
    }

    private function create(Response $response, array $data): Response
    {
        $propertyId = (int) ($data['property_id'] ?? 0);
        $prop = $this->pdo->prepare(
            'SELECT p.id, u.first_name AS owner_first_name, u.email AS owner_email
             FROM properties p LEFT JOIN users u ON u.id = p.owner_user_id
             WHERE p.id = :id AND p.agency_id = 1'
        );
        $prop->execute(['id' => $propertyId]);
        $property = $prop->fetch();
        if (!$property) {
            return $this->error($response, 'validation', 'Immobile non valido.', 422);
        }

        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
            return $this->error($response, 'validation', 'Importo proposta obbligatorio e positivo.', 422);
        }

        $status = $this->str($data, 'status', 20) ?? 'ricevuta';
        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            return $this->error($response, 'validation', 'Status non valido.', 422);
        }

        $receivedAt = $this->str($data, 'received_at', 40);
        if ($receivedAt !== null && strtotime($receivedAt) === false) {
            return $this->error($response, 'validation', 'Formato received_at non valido.', 422);
        }

        $visibleToOwner = array_key_exists('visible_to_owner', $data)
            ? filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $stmt = $this->pdo->prepare(
            'INSERT INTO proposals (agency_id, property_id, amount, status, received_at, notes, visible_to_owner)
             VALUES (1, :pid, :amount, :status, COALESCE(:received, now()), :notes, :visible)
             RETURNING id'
        );
        $stmt->execute([
            'pid' => $propertyId,
            'amount' => (string) round((float) $data['amount'], 2),
            'status' => $status,
            'received' => $receivedAt,
            'notes' => $this->str($data, 'notes', 3000),
            'visible' => $visibleToOwner ? 'true' : 'false',
        ]);
        $proposalId = (int) $stmt->fetchColumn();

        // Email SENZA importo (regola §6): solo invito ad accedere all'app
        if ($visibleToOwner && !empty($property['owner_email'])) {
            $this->mailer->send($property['owner_email'], 'nuova_proposta', [
                'first_name' => $property['owner_first_name'],
            ], 'proposal', $proposalId);
        }

        return $this->fetchAndReturn($response, $proposalId, 201);
    }

    private function update(Response $response, int $id, array $data): Response
    {
        $exists = $this->pdo->prepare('SELECT id FROM proposals WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Proposta non trovata.', 404);
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
        if (array_key_exists('amount', $data)) {
            if (!is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
                return $this->error($response, 'validation', 'Importo non valido.', 422);
            }
            $sets[] = 'amount = :amount';
            $params['amount'] = (string) round((float) $data['amount'], 2);
        }
        if (array_key_exists('notes', $data)) {
            $sets[] = 'notes = :notes';
            $params['notes'] = $this->str($data, 'notes', 3000);
        }
        if (array_key_exists('visible_to_owner', $data)) {
            $sets[] = 'visible_to_owner = :visible';
            $params['visible'] = filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE proposals SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $this->fetchAndReturn($response, $id, 200);
    }

    private function fetchAndReturn(Response $response, int $id, int $status): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM proposals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $this->json($response, ['data' => $stmt->fetch()], $status);
    }
}
