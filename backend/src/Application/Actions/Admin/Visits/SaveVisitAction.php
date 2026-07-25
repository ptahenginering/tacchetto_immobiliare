<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Visits;

use App\Application\Actions\Action;
use App\Domain\Mail\MailerInterface;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/admin/visits (crea; se visibile al proprietario invia email
 *      nuovo_feedback — o nuova_visita se senza feedback)
 * PUT  /api/admin/visits/{id} (aggiorna)
 *
 * Nota privacy: visitor_label è un'etichetta generica ("Coppia, prima casa"),
 * mai dati personali completi del visitatore.
 */
final class SaveVisitAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $data = $this->body($request);

        if ($id === null) {
            return $this->create($response, $data);
        }
        return $this->update($response, $id, $data);
    }

    private function create(Response $response, array $data): Response
    {
        $propertyId = (int) ($data['property_id'] ?? 0);
        $prop = $this->pdo->prepare(
            'SELECT p.id, p.owner_user_id, u.first_name AS owner_first_name, u.email AS owner_email
             FROM properties p LEFT JOIN users u ON u.id = p.owner_user_id
             WHERE p.id = :id AND p.agency_id = 1'
        );
        $prop->execute(['id' => $propertyId]);
        $property = $prop->fetch();
        if (!$property) {
            return $this->error($response, 'validation', 'Immobile non valido.', 422);
        }

        $visitedAt = $this->str($data, 'visited_at', 40);
        if ($visitedAt === null || strtotime($visitedAt) === false) {
            return $this->error($response, 'validation', 'Data visita (visited_at) obbligatoria.', 422);
        }

        $visitorLabel = $this->str($data, 'visitor_label', 150);
        if ($visitorLabel === null) {
            return $this->error($response, 'validation', 'Etichetta visitatore obbligatoria (es. "Coppia, prima casa").', 422);
        }

        $rating = $this->ratingOrNull($data);
        if ($rating === false) {
            return $this->error($response, 'validation', 'Rating non valido (1–5).', 422);
        }

        $appointmentId = null;
        if (!empty($data['appointment_id'])) {
            $check = $this->pdo->prepare('SELECT id FROM appointments WHERE id = :id AND agency_id = 1');
            $check->execute(['id' => (int) $data['appointment_id']]);
            $appointmentId = $check->fetch() ? (int) $data['appointment_id'] : null;
        }

        $feedbackText = $this->str($data, 'feedback_text', 5000);
        $visibleToOwner = array_key_exists('visible_to_owner', $data)
            ? filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $stmt = $this->pdo->prepare(
            'INSERT INTO visits (agency_id, property_id, appointment_id, visited_at, visitor_label, qualified,
                                 feedback_text, feedback_rating, visible_to_owner)
             VALUES (1, :pid, :aid, :visited, :label, :qualified, :feedback, :rating, :visible)
             RETURNING id'
        );
        $stmt->execute([
            'pid' => $propertyId,
            'aid' => $appointmentId,
            'visited' => $visitedAt,
            'label' => $visitorLabel,
            'qualified' => (array_key_exists('qualified', $data) ? filter_var($data['qualified'], FILTER_VALIDATE_BOOLEAN) : true) ? 'true' : 'false',
            'feedback' => $feedbackText,
            'rating' => $rating,
            'visible' => $visibleToOwner ? 'true' : 'false',
        ]);
        $visitId = (int) $stmt->fetchColumn();

        // Email al proprietario SOLO se l'elemento è visibile (regola §6)
        if ($visibleToOwner && !empty($property['owner_email'])) {
            $template = $feedbackText !== null ? 'nuovo_feedback' : 'nuova_visita';
            $this->mailer->send($property['owner_email'], $template, [
                'first_name' => $property['owner_first_name'],
            ], 'visit', $visitId);
        }

        return $this->fetchAndReturn($response, $visitId, 201);
    }

    private function update(Response $response, int $id, array $data): Response
    {
        $exists = $this->pdo->prepare('SELECT id FROM visits WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) {
            return $this->error($response, 'not_found', 'Visita non trovata.', 404);
        }

        $sets = [];
        $params = ['id' => $id];

        if (array_key_exists('visited_at', $data)) {
            $v = $this->str($data, 'visited_at', 40);
            if ($v === null || strtotime($v) === false) {
                return $this->error($response, 'validation', 'Formato visited_at non valido.', 422);
            }
            $sets[] = 'visited_at = :visited_at';
            $params['visited_at'] = $v;
        }
        if (array_key_exists('visitor_label', $data)) {
            $sets[] = 'visitor_label = :visitor_label';
            $params['visitor_label'] = $this->str($data, 'visitor_label', 150) ?? '';
        }
        if (array_key_exists('feedback_text', $data)) {
            $sets[] = 'feedback_text = :feedback_text';
            $params['feedback_text'] = $this->str($data, 'feedback_text', 5000);
        }
        if (array_key_exists('feedback_rating', $data)) {
            $rating = $this->ratingOrNull($data);
            if ($rating === false) {
                return $this->error($response, 'validation', 'Rating non valido (1–5).', 422);
            }
            $sets[] = 'feedback_rating = :feedback_rating';
            $params['feedback_rating'] = $rating;
        }
        if (array_key_exists('qualified', $data)) {
            $sets[] = 'qualified = :qualified';
            $params['qualified'] = filter_var($data['qualified'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        if (array_key_exists('visible_to_owner', $data)) {
            $sets[] = 'visible_to_owner = :visible_to_owner';
            $params['visible_to_owner'] = filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE visits SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return $this->fetchAndReturn($response, $id, 200);
    }

    /** @return int|null|false false = valore fornito ma invalido */
    private function ratingOrNull(array $data): int|null|false
    {
        if (!isset($data['feedback_rating']) || $data['feedback_rating'] === null || $data['feedback_rating'] === '') {
            return null;
        }
        $r = (int) $data['feedback_rating'];
        return ($r >= 1 && $r <= 5) ? $r : false;
    }

    private function fetchAndReturn(Response $response, int $id, int $status): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM visits WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $this->json($response, ['data' => $stmt->fetch()], $status);
    }
}
