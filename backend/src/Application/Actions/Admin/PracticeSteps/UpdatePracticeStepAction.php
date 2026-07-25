<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\PracticeSteps;

use App\Application\Actions\Action;
use App\Domain\Mail\MailerInterface;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PUT /api/admin/practice-steps/{id} — aggiorna stato step burocrazia.
 * Al passaggio a "completato" (se visibile) invia email step_completato al proprietario.
 */
final class UpdatePracticeStepAction extends Action
{
    private const ALLOWED_STATUS = ['da_fare', 'in_corso', 'completato'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $data = $this->body($request);

        $stmt = $this->pdo->prepare(
            'SELECT ps.*, u.first_name AS owner_first_name, u.email AS owner_email
             FROM practice_steps ps
             JOIN properties p ON p.id = ps.property_id
             LEFT JOIN users u ON u.id = p.owner_user_id
             WHERE ps.id = :id AND ps.agency_id = 1'
        );
        $stmt->execute(['id' => $id]);
        $step = $stmt->fetch();

        if (!$step) {
            return $this->error($response, 'not_found', 'Step non trovato.', 404);
        }

        $sets = [];
        $params = ['id' => $id];
        $becameCompleted = false;

        if (array_key_exists('status', $data)) {
            if (!in_array($data['status'], self::ALLOWED_STATUS, true)) {
                return $this->error($response, 'validation', 'Status non valido.', 422);
            }
            $sets[] = 'status = :status';
            $params['status'] = $data['status'];

            if ($data['status'] === 'completato' && $step['status'] !== 'completato') {
                $sets[] = 'completed_at = now()';
                $becameCompleted = true;
            } elseif ($data['status'] !== 'completato') {
                $sets[] = 'completed_at = NULL';
            }
        }

        if (array_key_exists('visible_to_owner', $data)) {
            $sets[] = 'visible_to_owner = :visible';
            $params['visible'] = filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        if ($sets === []) {
            return $this->error($response, 'validation', 'Nessun campo da aggiornare.', 422);
        }

        $sets[] = 'updated_at = now()';
        $this->pdo->prepare('UPDATE practice_steps SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        // Email solo se lo step è visibile al proprietario (regola §6)
        $stillVisible = array_key_exists('visible_to_owner', $data)
            ? filter_var($data['visible_to_owner'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $step['visible_to_owner'];

        if ($becameCompleted && $stillVisible && !empty($step['owner_email'])) {
            $this->mailer->send($step['owner_email'], 'step_completato', [
                'first_name' => $step['owner_first_name'],
                'step_label' => $step['label'],
            ], 'practice_step', $id);
        }

        $out = $this->pdo->prepare('SELECT * FROM practice_steps WHERE id = :id');
        $out->execute(['id' => $id]);

        return $this->json($response, ['data' => $out->fetch()]);
    }
}
