<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Proposals;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/proposals[?property_id][&status] — pipeline proposte.
 */
final class ListProposalsAction extends Action
{
    private const ALLOWED_STATUS = ['ricevuta', 'in_trattativa', 'accettata', 'rifiutata', 'ritirata'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();

        $where = ['pr.agency_id = 1'];
        $params = [];

        if (!empty($q['property_id'])) {
            $where[] = 'pr.property_id = :pid';
            $params['pid'] = (int) $q['property_id'];
        }
        if (isset($q['status']) && in_array($q['status'], self::ALLOWED_STATUS, true)) {
            $where[] = 'pr.status = :status';
            $params['status'] = $q['status'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT pr.*, p.title AS property_title
             FROM proposals pr
             JOIN properties p ON p.id = pr.property_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY pr.received_at DESC'
        );
        $stmt->execute($params);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }
}
