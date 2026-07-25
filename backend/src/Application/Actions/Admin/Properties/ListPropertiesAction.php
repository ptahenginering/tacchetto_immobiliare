<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Properties;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/properties — lista immobili con filtri e conteggi rapidi.
 */
final class ListPropertiesAction extends Action
{
    private const ALLOWED_STATUS = ['valutazione', 'in_vendita', 'in_trattativa', 'venduto', 'sospeso'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();

        $where = ['p.agency_id = 1'];
        $params = [];

        if (isset($q['status']) && in_array($q['status'], self::ALLOWED_STATUS, true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $q['status'];
        }
        if (!empty($q['search'])) {
            $where[] = '(p.title ILIKE :search OR p.address ILIKE :search OR p.city ILIKE :search)';
            $params['search'] = '%' . trim((string) $q['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);
        $page = max(1, (int) ($q['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($q['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM properties p WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.title, p.address, p.city, p.province, p.type, p.surface_sqm, p.rooms,
                    p.price, p.status, p.cover_image_url, p.mandate_start, p.mandate_end, p.created_at,
                    u.first_name AS owner_first_name, u.last_name AS owner_last_name,
                    (SELECT COUNT(*) FROM visits v WHERE v.property_id = p.id) AS visits_count,
                    (SELECT COUNT(*) FROM proposals pr WHERE pr.property_id = p.id AND pr.status IN ('ricevuta','in_trattativa')) AS active_proposals
             FROM properties p
             LEFT JOIN users u ON u.id = p.owner_user_id
             WHERE {$whereSql}
             ORDER BY p.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $this->json($response, [
            'data' => $stmt->fetchAll(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)],
        ]);
    }
}
