<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Marketing;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/marketing[?property_id][&channel] — registro attività promozione.
 */
final class ListMarketingAction extends Action
{
    private const ALLOWED_CHANNELS = ['immobiliare_it', 'idealista', 'facebook', 'instagram', 'linkedin', 'vetrina', 'portale_tecnocasa', 'altro'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();

        $where = ['m.agency_id = 1'];
        $params = [];

        if (!empty($q['property_id'])) {
            $where[] = 'm.property_id = :pid';
            $params['pid'] = (int) $q['property_id'];
        }
        if (isset($q['channel']) && in_array($q['channel'], self::ALLOWED_CHANNELS, true)) {
            $where[] = 'm.channel = :channel';
            $params['channel'] = $q['channel'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT m.*, p.title AS property_title
             FROM marketing_activities m
             JOIN properties p ON p.id = m.property_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY m.published_at DESC NULLS LAST, m.created_at DESC'
        );
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['stats'] = json_decode((string) $row['stats'], true) ?: (object) [];
        }

        return $this->json($response, ['data' => $rows]);
    }
}
