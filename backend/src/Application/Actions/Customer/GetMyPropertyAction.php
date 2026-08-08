<?php

declare(strict_types=1);

namespace App\Application\Actions\Customer;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/customer/properties — tutti gli immobili del proprietario loggato.
 * GET /api/customer/property?property_id= — dettaglio di un immobile (default: il più recente).
 * Enforcement a livello di query: owner_user_id = uid del token.
 */
final class GetMyPropertyAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Lista immobili del proprietario (per il selettore multiproprietà). */
    public function list(Request $request, Response $response): Response
    {
        $uid = (int) $request->getAttribute('auth_uid');

        $stmt = $this->pdo->prepare(
            'SELECT id, title, address, city, province, type, status, cover_image_url, created_at
             FROM properties
             WHERE owner_user_id = :uid AND agency_id = 1
             ORDER BY created_at DESC'
        );
        $stmt->execute(['uid' => $uid]);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $uid = (int) $request->getAttribute('auth_uid');
        $requestedId = (int) ($request->getQueryParams()['property_id'] ?? 0);

        $sql = 'SELECT id, title, address, city, province, type, surface_sqm, rooms, price, status,
                       cover_image_url, description, mandate_start, mandate_end, created_at
                FROM properties
                WHERE owner_user_id = :uid AND agency_id = 1';
        $params = ['uid' => $uid];
        if ($requestedId > 0) {
            $sql .= ' AND id = :pid';
            $params['pid'] = $requestedId;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
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
