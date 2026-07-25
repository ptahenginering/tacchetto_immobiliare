<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Appointments;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/admin/appointments (crea)
 * PUT  /api/admin/appointments/{id} (aggiorna)
 */
final class SaveAppointmentAction extends Action
{
    private const ALLOWED_TYPES = ['valutazione', 'visita', 'firma', 'altro'];
    private const ALLOWED_STATUS = ['programmato', 'svolto', 'annullato'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $data = $this->body($request);

        $startsAt = $this->str($data, 'starts_at', 40);
        if ($id === null && ($startsAt === null || strtotime($startsAt) === false)) {
            return $this->error($response, 'validation', 'Data/ora inizio (starts_at) obbligatoria.', 422);
        }
        if ($startsAt !== null && strtotime($startsAt) === false) {
            return $this->error($response, 'validation', 'Formato starts_at non valido.', 422);
        }

        $endsAt = $this->str($data, 'ends_at', 40);
        if ($endsAt !== null && strtotime($endsAt) === false) {
            return $this->error($response, 'validation', 'Formato ends_at non valido.', 422);
        }

        $type = $this->str($data, 'type', 20);
        if ($type !== null && !in_array($type, self::ALLOWED_TYPES, true)) {
            return $this->error($response, 'validation', 'Tipo non valido.', 422);
        }

        $status = $this->str($data, 'status', 20);
        if ($status !== null && !in_array($status, self::ALLOWED_STATUS, true)) {
            return $this->error($response, 'validation', 'Status non valido.', 422);
        }

        $propertyId = $this->fkOrNull($data, 'property_id', 'properties');
        if ($propertyId === false) {
            return $this->error($response, 'validation', 'Immobile non valido.', 422);
        }
        $leadId = $this->fkOrNull($data, 'lead_id', 'leads');
        if ($leadId === false) {
            return $this->error($response, 'validation', 'Lead non valido.', 422);
        }

        if ($id === null) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO appointments (agency_id, property_id, lead_id, type, starts_at, ends_at, contact_name, contact_phone, status, notes)
                 VALUES (1, :pid, :lid, :type, :starts, :ends, :cname, :cphone, :status, :notes)
                 RETURNING id"
            );
            $stmt->execute([
                'pid' => $propertyId,
                'lid' => $leadId,
                'type' => $type ?? 'visita',
                'starts' => $startsAt,
                'ends' => $endsAt,
                'cname' => $this->str($data, 'contact_name', 150),
                'cphone' => $this->str($data, 'contact_phone', 50),
                'status' => $status ?? 'programmato',
                'notes' => $this->str($data, 'notes', 3000),
            ]);
            $id = (int) $stmt->fetchColumn();
            return $this->fetchAndReturn($response, $id, 201);
        }

        $exists = $this->pdo->prepare('SELECT id FROM appointments WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Appuntamento non trovato.', 404);
        }

        $sets = [];
        $params = ['id' => $id];

        $map = [
            'type' => $type,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'contact_name' => $this->str($data, 'contact_name', 150),
            'contact_phone' => $this->str($data, 'contact_phone', 50),
            'notes' => $this->str($data, 'notes', 3000),
        ];
        foreach ($map as $col => $val) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $val;
            }
        }
        if (array_key_exists('property_id', $data)) {
            $sets[] = 'property_id = :property_id';
            $params['property_id'] = $propertyId;
        }
        if (array_key_exists('lead_id', $data)) {
            $sets[] = 'lead_id = :lead_id';
            $params['lead_id'] = $leadId;
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE appointments SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $this->fetchAndReturn($response, $id, 200);
    }

    /** @return int|null|false false = id fornito ma inesistente */
    private function fkOrNull(array $data, string $key, string $table): int|null|false
    {
        if (!isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT id FROM {$table} WHERE id = :id AND agency_id = 1");
        $stmt->execute(['id' => (int) $data[$key]]);
        return $stmt->fetch() ? (int) $data[$key] : false;
    }

    private function fetchAndReturn(Response $response, int $id, int $status): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM appointments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $this->json($response, ['data' => $stmt->fetch()], $status);
    }
}
