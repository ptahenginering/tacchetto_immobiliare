<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Leads;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/leads — lista con filtri (status, source, ricerca, date) e paginazione server-side.
 */
final class ListLeadsAction extends Action
{
    private const ALLOWED_STATUS = ['nuovo', 'contattato', 'appuntamento', 'incarico', 'perso'];
    private const ALLOWED_SOURCE = ['sito', 'qr', 'social', 'referral', 'altro'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();

        $where = ['l.agency_id = 1'];
        $params = [];

        if (isset($q['status']) && in_array($q['status'], self::ALLOWED_STATUS, true)) {
            $where[] = 'l.status = :status';
            $params['status'] = $q['status'];
        }
        if (isset($q['source']) && in_array($q['source'], self::ALLOWED_SOURCE, true)) {
            $where[] = 'l.source = :source';
            $params['source'] = $q['source'];
        }
        if (!empty($q['search'])) {
            $where[] = "(l.first_name ILIKE :search OR l.last_name ILIKE :search OR l.email ILIKE :search OR l.phone ILIKE :search)";
            $params['search'] = '%' . trim((string) $q['search']) . '%';
        }
        if (!empty($q['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['from'])) {
            $where[] = 'l.created_at >= :from';
            $params['from'] = $q['from'];
        }
        if (!empty($q['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['to'])) {
            $where[] = "l.created_at < (:to)::date + interval '1 day'";
            $params['to'] = $q['to'];
        }

        $whereSql = implode(' AND ', $where);

        $page = max(1, (int) ($q['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($q['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM leads l WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT l.id, l.first_name, l.last_name, l.email, l.phone, l.request_type, l.message,
                    l.source, l.status, l.assigned_to, l.notes, l.converted_property_id, l.lost_reason,
                    l.created_at, l.updated_at,
                    u.first_name AS assigned_first_name, u.last_name AS assigned_last_name
             FROM leads l
             LEFT JOIN users u ON u.id = l.assigned_to
             WHERE {$whereSql}
             ORDER BY l.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $this->json($response, [
            'data' => $stmt->fetchAll(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }
}
