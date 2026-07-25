<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Appointments;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/appointments?from=YYYY-MM-DD&to=YYYY-MM-DD[&property_id][&status]
 * Dati per vista calendario e lista.
 */
final class ListAppointmentsAction extends Action
{
    private const ALLOWED_STATUS = ['programmato', 'svolto', 'annullato'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();

        $where = ['a.agency_id = 1'];
        $params = [];

        if (!empty($q['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['from'])) {
            $where[] = 'a.starts_at >= :from';
            $params['from'] = $q['from'];
        }
        if (!empty($q['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['to'])) {
            $where[] = "a.starts_at < (:to)::date + interval '1 day'";
            $params['to'] = $q['to'];
        }
        if (!empty($q['property_id'])) {
            $where[] = 'a.property_id = :pid';
            $params['pid'] = (int) $q['property_id'];
        }
        if (isset($q['status']) && in_array($q['status'], self::ALLOWED_STATUS, true)) {
            $where[] = 'a.status = :status';
            $params['status'] = $q['status'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.*, p.title AS property_title, p.address AS property_address,
                    l.first_name AS lead_first_name, l.last_name AS lead_last_name
             FROM appointments a
             LEFT JOIN properties p ON p.id = a.property_id
             LEFT JOIN leads l ON l.id = a.lead_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.starts_at'
        );
        $stmt->execute($params);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }
}
