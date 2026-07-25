<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Leads;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/leads/{id} — dettaglio lead con appuntamenti collegati.
 */
final class GetLeadAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            'SELECT l.*, u.first_name AS assigned_first_name, u.last_name AS assigned_last_name,
                    p.title AS property_title, p.status AS property_status
             FROM leads l
             LEFT JOIN users u ON u.id = l.assigned_to
             LEFT JOIN properties p ON p.id = l.converted_property_id
             WHERE l.id = :id AND l.agency_id = 1'
        );
        $stmt->execute(['id' => $id]);
        $lead = $stmt->fetch();

        if (!$lead) {
            return $this->error($response, 'not_found', 'Lead non trovato.', 404);
        }

        $apptStmt = $this->pdo->prepare(
            'SELECT id, type, starts_at, ends_at, status, notes
             FROM appointments WHERE lead_id = :id ORDER BY starts_at DESC'
        );
        $apptStmt->execute(['id' => $id]);
        $lead['appointments'] = $apptStmt->fetchAll();

        return $this->json($response, ['data' => $lead]);
    }
}
