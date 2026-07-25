<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Marketing;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/admin/marketing (crea)
 * PUT  /api/admin/marketing/{id} (aggiorna, stats JSONB editabili)
 */
final class SaveMarketingAction extends Action
{
    private const ALLOWED_CHANNELS = ['immobiliare_it', 'idealista', 'facebook', 'instagram', 'linkedin', 'vetrina', 'portale_tecnocasa', 'altro'];
    private const ALLOWED_TYPES = ['pubblicazione', 'campagna', 'post', 'open_house', 'altro'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $data = $this->body($request);

        $channel = $this->str($data, 'channel', 30);
        if ($channel !== null && !in_array($channel, self::ALLOWED_CHANNELS, true)) {
            return $this->error($response, 'validation', 'Canale non valido.', 422);
        }
        $activityType = $this->str($data, 'activity_type', 20);
        if ($activityType !== null && !in_array($activityType, self::ALLOWED_TYPES, true)) {
            return $this->error($response, 'validation', 'Tipo attività non valido.', 422);
        }

        $publishedAt = $this->str($data, 'published_at', 40);
        if ($publishedAt !== null && strtotime($publishedAt) === false) {
            return $this->error($response, 'validation', 'Formato published_at non valido.', 422);
        }

        $stats = null;
        if (array_key_exists('stats', $data)) {
            if (!is_array($data['stats'])) {
                return $this->error($response, 'validation', 'stats deve essere un oggetto (es. {"views":1234,"contacts":8}).', 422);
            }
            $stats = json_encode($data['stats'], JSON_UNESCAPED_UNICODE);
        }

        if ($id === null) {
            $propertyId = (int) ($data['property_id'] ?? 0);
            $prop = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id AND agency_id = 1');
            $prop->execute(['id' => $propertyId]);
            if (!$prop->fetch()) {
                return $this->error($response, 'validation', 'Immobile non valido.', 422);
            }

            $title = $this->str($data, 'title');
            if ($channel === null || $title === null) {
                return $this->error($response, 'validation', 'Canale e titolo sono obbligatori.', 422);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO marketing_activities (agency_id, property_id, channel, activity_type, title, url, published_at, stats, visible_to_owner)
                 VALUES (1, :pid, :channel, :type, :title, :url, :published, COALESCE(:stats, \'{}\')::jsonb, :visible)
                 RETURNING id'
            );
            $stmt->execute([
                'pid' => $propertyId,
                'channel' => $channel,
                'type' => $activityType ?? 'pubblicazione',
                'title' => $title,
                'url' => $this->str($data, 'url', 500),
                'published' => $publishedAt,
                'stats' => $stats,
                'visible' => (array_key_exists('visible_to_owner', $data) ? filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN) : true) ? 'true' : 'false',
            ]);

            return $this->fetchAndReturn($response, (int) $stmt->fetchColumn(), 201);
        }

        $exists = $this->pdo->prepare('SELECT id FROM marketing_activities WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Attività non trovata.', 404);
        }

        $sets = [];
        $params = ['id' => $id];

        foreach (['channel' => $channel, 'activity_type' => $activityType, 'published_at' => $publishedAt] as $col => $val) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $val;
            }
        }
        if (array_key_exists('title', $data)) {
            $sets[] = 'title = :title';
            $params['title'] = $this->str($data, 'title') ?? '';
        }
        if (array_key_exists('url', $data)) {
            $sets[] = 'url = :url';
            $params['url'] = $this->str($data, 'url', 500);
        }
        if ($stats !== null) {
            $sets[] = 'stats = (:stats)::jsonb';
            $params['stats'] = $stats;
        }
        if (array_key_exists('visible_to_owner', $data)) {
            $sets[] = 'visible_to_owner = :visible';
            $params['visible'] = filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE marketing_activities SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $this->fetchAndReturn($response, $id, 200);
    }

    private function fetchAndReturn(Response $response, int $id, int $status): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketing_activities WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        $row['stats'] = json_decode((string) $row['stats'], true) ?: (object) [];
        return $this->json($response, ['data' => $row], $status);
    }
}
