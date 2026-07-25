<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Properties;

use App\Application\Actions\Action;
use App\Infrastructure\Upload\ImageUploadService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * POST /api/admin/properties/{id}/images — upload multipart (campo "images[]" o "image").
 * Salvataggio in storage/uploads/properties/{id}/, resize GD a max 1600px.
 * La prima immagine caricata diventa cover se l'immobile non ne ha una.
 */
final class UploadPropertyImagesAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ImageUploadService $uploads,
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $propertyId = (int) ($args['id'] ?? 0);

        $prop = $this->pdo->prepare('SELECT id, cover_image_url FROM properties WHERE id = :id AND agency_id = 1');
        $prop->execute(['id' => $propertyId]);
        $property = $prop->fetch();
        if (!$property) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        $files = $request->getUploadedFiles();
        $list = [];
        if (isset($files['images']) && is_array($files['images'])) {
            $list = $files['images'];
        } elseif (isset($files['image'])) {
            $list = [$files['image']];
        }

        if ($list === []) {
            return $this->error($response, 'validation', 'Nessun file ricevuto (campo "images[]" o "image").', 422);
        }

        $maxSort = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM property_images WHERE property_id = :id');
        $maxSort->execute(['id' => $propertyId]);
        $sort = (int) $maxSort->fetchColumn();

        $saved = [];
        $errors = [];

        foreach ($list as $i => $file) {
            try {
                $relPath = $this->uploads->store($file, 'properties/' . $propertyId);
                $sort += 10;

                $ins = $this->pdo->prepare(
                    'INSERT INTO property_images (agency_id, property_id, url, sort_order)
                     VALUES (1, :pid, :url, :sort) RETURNING id'
                );
                $ins->execute(['pid' => $propertyId, 'url' => $relPath, 'sort' => $sort]);

                $saved[] = [
                    'id' => (int) $ins->fetchColumn(),
                    'url' => $relPath,
                    'sort_order' => $sort,
                ];
            } catch (Throwable $e) {
                $errors[] = ['index' => $i, 'message' => $e->getMessage()];
            }
        }

        // Auto-cover con la prima immagine se manca
        if ($saved !== [] && empty($property['cover_image_url'])) {
            $this->pdo->prepare('UPDATE properties SET cover_image_url = :url, updated_at = now() WHERE id = :id')
                ->execute(['url' => $saved[0]['url'], 'id' => $propertyId]);
        }

        if ($saved === []) {
            return $this->json($response, ['error' => ['code' => 'upload_failed', 'message' => 'Nessuna immagine salvata.', 'details' => $errors]], 422);
        }

        return $this->json($response, ['data' => $saved, 'errors' => $errors], 201);
    }
}
