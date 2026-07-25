<?php

declare(strict_types=1);

namespace App\Application\Actions\Customer;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/customer/property — l'immobile del proprietario loggato.
 * Enforcement a livello di query: owner_user_id = uid del token.
 */
final class GetMyPropertyAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $uid = (int) $request->getAttribute('auth_uid');

        $stmt = $this->pdo->prepare(
            'SELECT id, title, address, city, province, type, surface_sqm, rooms, price, status,
                    cover_image_url, description, mandate_start, mandate_end, created_at
             FROM properties
             WHERE owner_user_id = :uid AND agency_id = 1
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute(['uid' => $uid]);
        $property = $stmt->fetch();

        if (!$property) {
            return $this->error($response, 'no_property', 'Nessun immobile associato al tuo profilo. Contatta Roberto per maggiori informazioni.', 404);
        }

        $imgs = $this->pdo->prepare(
            'SELECT url, sort_order FROM property_images WHERE property_id = :id ORDER BY sort_order, id'
        );
        $imgs->execute(['id' => $property['id']]);
        $property['images'] = $imgs->fetchAll();

        return $this->json($response, ['data' => $property]);
    }
}
