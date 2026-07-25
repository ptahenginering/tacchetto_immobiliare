<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Properties;

use App\Application\Actions\Action;
use App\Infrastructure\Upload\ImageUploadService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PUT    /api/admin/properties/{id}/images/order — riordino: {order: [imageId, ...]}
 * PUT    /api/admin/properties/{id}/images/{imageId}/cover — imposta come cover
 * DELETE /api/admin/properties/{id}/images/{imageId} — elimina immagine
 */
final class ManagePropertyImageAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ImageUploadService $uploads,
    ) {
    }

    public function reorder(Request $request, Response $response, array $args): Response
    {
        $propertyId = (int) ($args['id'] ?? 0);
        if (!$this->propertyExists($propertyId)) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        $order = $this->body($request)['order'] ?? null;
        if (!is_array($order) || $order === []) {
            return $this->error($response, 'validation', 'Campo "order" (array di id) obbligatorio.', 422);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE property_images SET sort_order = :sort, updated_at = now()
             WHERE id = :id AND property_id = :pid'
        );
        foreach (array_values($order) as $i => $imageId) {
            $stmt->execute(['sort' => ($i + 1) * 10, 'id' => (int) $imageId, 'pid' => $propertyId]);
        }

        return $this->json($response, ['ok' => true]);
    }

    public function setCover(Request $request, Response $response, array $args): Response
    {
        $propertyId = (int) ($args['id'] ?? 0);
        $imageId = (int) ($args['imageId'] ?? 0);

        $img = $this->pdo->prepare('SELECT url FROM property_images WHERE id = :id AND property_id = :pid');
        $img->execute(['id' => $imageId, 'pid' => $propertyId]);
        $row = $img->fetch();
        if (!$row) {
            return $this->error($response, 'not_found', 'Immagine non trovata.', 404);
        }

        $this->pdo->prepare('UPDATE properties SET cover_image_url = :url, updated_at = now() WHERE id = :id AND agency_id = 1')
            ->execute(['url' => $row['url'], 'id' => $propertyId]);

        return $this->json($response, ['ok' => true, 'cover_image_url' => $row['url']]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $propertyId = (int) ($args['id'] ?? 0);
        $imageId = (int) ($args['imageId'] ?? 0);

        $img = $this->pdo->prepare('SELECT url FROM property_images WHERE id = :id AND property_id = :pid');
        $img->execute(['id' => $imageId, 'pid' => $propertyId]);
        $row = $img->fetch();
        if (!$row) {
            return $this->error($response, 'not_found', 'Immagine non trovata.', 404);
        }

        $this->uploads->delete((string) $row['url']);
        $this->pdo->prepare('DELETE FROM property_images WHERE id = :id')->execute(['id' => $imageId]);

        // Se era la cover, passa alla prima rimasta
        $prop = $this->pdo->prepare('SELECT cover_image_url FROM properties WHERE id = :id');
        $prop->execute(['id' => $propertyId]);
        $cover = $prop->fetchColumn();
        if ($cover === $row['url']) {
            $next = $this->pdo->prepare('SELECT url FROM property_images WHERE property_id = :pid ORDER BY sort_order, id LIMIT 1');
            $next->execute(['pid' => $propertyId]);
            $nextUrl = $next->fetchColumn() ?: null;
            $this->pdo->prepare('UPDATE properties SET cover_image_url = :url, updated_at = now() WHERE id = :id')
                ->execute(['url' => $nextUrl, 'id' => $propertyId]);
        }

        return $this->json($response, ['ok' => true]);
    }

    private function propertyExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id AND agency_id = 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetch();
    }
}
