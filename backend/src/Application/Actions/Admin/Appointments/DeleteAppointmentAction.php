<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Appointments;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DELETE /api/admin/appointments/{id}
 */
final class DeleteAppointmentAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare('DELETE FROM appointments WHERE id = :id AND agency_id = 1');
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'not_found', 'Appuntamento non trovato.', 404);
        }

        return $this->json($response, ['ok' => true]);
    }
}
