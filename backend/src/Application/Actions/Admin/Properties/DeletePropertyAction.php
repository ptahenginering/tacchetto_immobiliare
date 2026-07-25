<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Properties;

use App\Application\Actions\Action;
use App\Infrastructure\Upload\ImageUploadService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DELETE /api/admin/properties/{id} — elimina immobile e file immagini.
 * Le entità collegate cadono per ON DELETE CASCADE.
 */
final class DeletePropertyAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ImageUploadService $uploads,
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        $exists = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        // Rimuovi i file fisici delle immagini
        $imgs = $this->pdo->prepare('SELECT url FROM property_images WHERE property_id = :id');
        $imgs->execute(['id' => $id]);
        foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $url) {
            // url salvate come "properties/{id}/xxx.jpg"
            $this->uploads->delete((string) $url);
        }

        $this->pdo->prepare('DELETE FROM properties WHERE id = :id')->execute(['id' => $id]);

        return $this->json($response, ['ok' => true]);
    }
}
