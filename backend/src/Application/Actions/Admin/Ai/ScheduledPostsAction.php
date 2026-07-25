<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Ai;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Post programmati (predisposizione fase social — pubblicazione manuale):
 *   GET    /api/admin/scheduled-posts[?property_id][&status]
 *   POST   /api/admin/scheduled-posts
 *   PUT    /api/admin/scheduled-posts/{id}
 *   DELETE /api/admin/scheduled-posts/{id}
 */
final class ScheduledPostsAction extends Action
{
    private const ALLOWED_STATUS = ['bozza', 'programmato', 'pubblicato', 'errore'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $where = ['sp.agency_id = 1'];
        $params = [];

        if (!empty($q['property_id'])) {
            $where[] = 'sp.property_id = :pid';
            $params['pid'] = (int) $q['property_id'];
        }
        if (isset($q['status']) && in_array($q['status'], self::ALLOWED_STATUS, true)) {
            $where[] = 'sp.status = :status';
            $params['status'] = $q['status'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT sp.*, p.title AS property_title
             FROM scheduled_posts sp
             LEFT JOIN properties p ON p.id = sp.property_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY sp.created_at DESC'
        );
        $stmt->execute($params);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }

    public function save(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $data = $this->body($request);

        $status = $this->str($data, 'status', 20);
        if ($status !== null && !in_array($status, self::ALLOWED_STATUS, true)) {
            return $this->error($response, 'validation', 'Status non valido.', 422);
        }

        $scheduledAt = $this->str($data, 'scheduled_at', 40);
        if ($scheduledAt !== null && strtotime($scheduledAt) === false) {
            return $this->error($response, 'validation', 'Formato scheduled_at non valido.', 422);
        }

        if ($id === null) {
            $content = $this->str($data, 'content', 10000);
            $channel = $this->str($data, 'channel', 30);
            if ($content === null || $channel === null) {
                return $this->error($response, 'validation', 'Canale e contenuto obbligatori.', 422);
            }

            $propertyId = null;
            if (!empty($data['property_id'])) {
                $check = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id AND agency_id = 1');
                $check->execute(['id' => (int) $data['property_id']]);
                $propertyId = $check->fetch() ? (int) $data['property_id'] : null;
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO scheduled_posts (agency_id, property_id, channel, content, image_url, scheduled_at, status)
                 VALUES (1, :pid, :channel, :content, :image, :scheduled, :status) RETURNING id'
            );
            $stmt->execute([
                'pid' => $propertyId,
                'channel' => $channel,
                'content' => $content,
                'image' => $this->str($data, 'image_url', 500),
                'scheduled' => $scheduledAt,
                'status' => $status ?? 'bozza',
            ]);

            return $this->fetchAndReturn($response, (int) $stmt->fetchColumn(), 201);
        }

        $exists = $this->pdo->prepare('SELECT id FROM scheduled_posts WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Post non trovato.', 404);
        }

        $sets = [];
        $params = ['id' => $id];
        if (array_key_exists('content', $data)) {
            $sets[] = 'content = :content';
            $params['content'] = $this->str($data, 'content', 10000) ?? '';
        }
        if (array_key_exists('channel', $data)) {
            $sets[] = 'channel = :channel';
            $params['channel'] = $this->str($data, 'channel', 30) ?? 'altro';
        }
        if ($status !== null) {
            $sets[] = 'status = :status';
            $params['status'] = $status;
        }
        if (array_key_exists('scheduled_at', $data)) {
            $sets[] = 'scheduled_at = :scheduled';
            $params['scheduled'] = $scheduledAt;
        }
        if (array_key_exists('image_url', $data)) {
            $sets[] = 'image_url = :image';
            $params['image'] = $this->str($data, 'image_url', 500);
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE scheduled_posts SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $this->fetchAndReturn($response, $id, 200);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $stmt = $this->pdo->prepare('DELETE FROM scheduled_posts WHERE id = :id AND agency_id = 1');
        $stmt->execute(['id' => (int) ($args['id'] ?? 0)]);

        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'not_found', 'Post non trovato.', 404);
        }

        return $this->json($response, ['ok' => true]);
    }

    private function fetchAndReturn(Response $response, int $id, int $status): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scheduled_posts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $this->json($response, ['data' => $stmt->fetch()], $status);
    }
}
