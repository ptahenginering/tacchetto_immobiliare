<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Properties;

use App\Application\Actions\Action;
use App\Application\Services\PracticeStepSeeder;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/admin/properties (crea, con auto-seed step burocrazia)
 * PUT  /api/admin/properties/{id} (aggiorna)
 */
final class SavePropertyAction extends Action
{
    private const ALLOWED_TYPES = ['appartamento', 'casa', 'villa', 'terreno', 'commerciale', 'altro'];
    private const ALLOWED_STATUS = ['valutazione', 'in_vendita', 'in_trattativa', 'venduto', 'sospeso'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly PracticeStepSeeder $stepSeeder,
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $data = $this->body($request);

        // --- Validazione campi ---
        $title = $this->str($data, 'title');
        if ($id === null && $title === null) {
            return $this->error($response, 'validation', 'Il titolo è obbligatorio.', 422);
        }

        $type = $this->str($data, 'type', 20);
        if ($type !== null && !in_array($type, self::ALLOWED_TYPES, true)) {
            return $this->error($response, 'validation', 'Tipologia non valida.', 422);
        }

        $status = $this->str($data, 'status', 20);
        if ($status !== null && !in_array($status, self::ALLOWED_STATUS, true)) {
            return $this->error($response, 'validation', 'Stato non valido.', 422);
        }

        $ownerUserId = null;
        if (array_key_exists('owner_user_id', $data) && $data['owner_user_id'] !== null && $data['owner_user_id'] !== '') {
            $check = $this->pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'owner'");
            $check->execute(['id' => (int) $data['owner_user_id']]);
            if (!$check->fetch()) {
                return $this->error($response, 'validation', 'Proprietario non valido.', 422);
            }
            $ownerUserId = (int) $data['owner_user_id'];
        }

        $price = null;
        if (isset($data['price']) && $data['price'] !== '' && $data['price'] !== null) {
            if (!is_numeric($data['price']) || (float) $data['price'] < 0) {
                return $this->error($response, 'validation', 'Prezzo non valido.', 422);
            }
            $price = (string) round((float) $data['price'], 2);
        }

        $fields = [
            'title' => $title,
            'address' => $this->str($data, 'address'),
            'city' => $this->str($data, 'city', 100),
            'province' => $this->str($data, 'province', 10),
            'type' => $type,
            'status' => $status,
            'description' => $this->str($data, 'description', 10000),
            'cover_image_url' => $this->str($data, 'cover_image_url', 500),
            'surface_sqm' => isset($data['surface_sqm']) && is_numeric($data['surface_sqm']) ? (int) $data['surface_sqm'] : null,
            'rooms' => isset($data['rooms']) && is_numeric($data['rooms']) ? (int) $data['rooms'] : null,
            'price' => $price,
            'mandate_start' => $this->dateOrNull($data, 'mandate_start'),
            'mandate_end' => $this->dateOrNull($data, 'mandate_end'),
        ];

        if ($id === null) {
            // --- CREATE ---
            $stmt = $this->pdo->prepare(
                'INSERT INTO properties (agency_id, owner_user_id, title, address, city, province, type, surface_sqm,
                                         rooms, price, status, cover_image_url, description, mandate_start, mandate_end)
                 VALUES (1, :owner, :title, :address, :city, :province, :type, :sqm, :rooms, :price,
                         :status, :cover, :description, :m_start, :m_end)
                 RETURNING id'
            );
            $stmt->execute([
                'owner' => $ownerUserId,
                'title' => $fields['title'],
                'address' => $fields['address'],
                'city' => $fields['city'],
                'province' => $fields['province'],
                'type' => $fields['type'] ?? 'appartamento',
                'sqm' => $fields['surface_sqm'],
                'rooms' => $fields['rooms'],
                'price' => $fields['price'],
                'status' => $fields['status'] ?? 'valutazione',
                'cover' => $fields['cover_image_url'],
                'description' => $fields['description'],
                'm_start' => $fields['mandate_start'],
                'm_end' => $fields['mandate_end'],
            ]);
            $id = (int) $stmt->fetchColumn();

            $this->stepSeeder->seedForProperty($id, !empty($data['inherited']));

            return $this->fetchAndReturn($response, $id, 201);
        }

        // --- UPDATE ---
        $exists = $this->pdo->prepare('SELECT id FROM properties WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $col => $value) {
            if (array_key_exists($this->bodyKeyFor($col), $data)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $value;
            }
        }
        if (array_key_exists('owner_user_id', $data)) {
            $sets[] = 'owner_user_id = :owner_user_id';
            $params['owner_user_id'] = $ownerUserId;
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE properties SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $this->fetchAndReturn($response, $id, 200);
    }

    private function bodyKeyFor(string $column): string
    {
        return $column; // colonne e chiavi body coincidono
    }

    private function dateOrNull(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function fetchAndReturn(Response $response, int $id, int $status): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $this->json($response, ['data' => $stmt->fetch()], $status);
    }
}
