<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Properties;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/admin/properties/{id} — scheda immobile completa
 * (dati, proprietario, immagini, step burocrazia).
 */
final class GetPropertyAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.id AS owner_id, u.first_name AS owner_first_name, u.last_name AS owner_last_name,
                    u.email AS owner_email, u.phone AS owner_phone, u.last_login_at AS owner_last_login_at
             FROM properties p
             LEFT JOIN users u ON u.id = p.owner_user_id
             WHERE p.id = :id AND p.agency_id = 1'
        );
        $stmt->execute(['id' => $id]);
        $property = $stmt->fetch();

        if (!$property) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        $imgStmt = $this->pdo->prepare(
            'SELECT id, url, sort_order, is_ai_generated FROM property_images
             WHERE property_id = :id ORDER BY sort_order, id'
        );
        $imgStmt->execute(['id' => $id]);
        $property['images'] = $imgStmt->fetchAll();

        $stepStmt = $this->pdo->prepare(
            'SELECT id, step_key, label, status, sort_order, completed_at, visible_to_owner
             FROM practice_steps WHERE property_id = :id ORDER BY sort_order'
        );
        $stepStmt->execute(['id' => $id]);
        $property['practice_steps'] = $stepStmt->fetchAll();

        return $this->json($response, ['data' => $property]);
    }
}
