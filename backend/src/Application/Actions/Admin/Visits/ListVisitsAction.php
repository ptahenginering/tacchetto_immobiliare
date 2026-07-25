<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Visits;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/visits[?property_id][&from][&to] — lista visite con immobile.
 */
final class ListVisitsAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();

        $where = ['v.agency_id = 1'];
        $params = [];

        if (!empty($q['property_id'])) {
            $where[] = 'v.property_id = :pid';
            $params['pid'] = (int) $q['property_id'];
        }
        if (!empty($q['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['from'])) {
            $where[] = 'v.visited_at >= :from';
            $params['from'] = $q['from'];
        }
        if (!empty($q['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['to'])) {
            $where[] = "v.visited_at < (:to)::date + interval '1 day'";
            $params['to'] = $q['to'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT v.*, p.title AS property_title
             FROM visits v
             JOIN properties p ON p.id = v.property_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY v.visited_at DESC'
        );
        $stmt->execute($params);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }
}
