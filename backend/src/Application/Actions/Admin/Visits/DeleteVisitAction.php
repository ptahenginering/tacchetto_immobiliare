<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Visits;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DELETE /api/admin/visits/{id}
 */
final class DeleteVisitAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $stmt = $this->pdo->prepare('DELETE FROM visits WHERE id = :id AND agency_id = 1');
        $stmt->execute(['id' => (int) ($args['id'] ?? 0)]);

        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'not_found', 'Visita non trovata.', 404);
        }

        return $this->json($response, ['ok' => true]);
    }
}
