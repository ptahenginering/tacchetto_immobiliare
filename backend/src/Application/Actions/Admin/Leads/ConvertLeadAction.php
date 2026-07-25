<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Leads;

use App\Application\Actions\Action;
use App\Application\Services\MagicLinkService;
use App\Application\Services\PracticeStepSeeder;
use App\Domain\Mail\MailerInterface;
use App\Infrastructure\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * POST /api/admin/leads/{id}/convert — conversione lead → incarico.
 *
 * 1. Crea (o collega) l'utente owner a partire dai dati del lead
 * 2. Crea l'immobile in stato "valutazione" + seed step burocrazia
 * 3. Aggiorna il lead (status incarico, converted_property_id)
 * 4. Invia email benvenuto_proprietario con magic link (validità 7 giorni)
 */
final class ConvertLeadAction extends Action
{
    private const ALLOWED_TYPES = ['appartamento', 'casa', 'villa', 'terreno', 'commerciale', 'altro'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly MagicLinkService $magicLinks,
        private readonly PracticeStepSeeder $stepSeeder,
        private readonly MailerInterface $mailer,
        private readonly Logger $logger,
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $leadId = (int) ($args['id'] ?? 0);
        $data = $this->body($request);

        $stmt = $this->pdo->prepare('SELECT * FROM leads WHERE id = :id AND agency_id = 1');
        $stmt->execute(['id' => $leadId]);
        $lead = $stmt->fetch();

        if (!$lead) {
            return $this->error($response, 'not_found', 'Lead non trovato.', 404);
        }
        if ($lead['converted_property_id']) {
            return $this->error($response, 'already_converted', 'Lead già convertito in incarico.', 409);
        }

        // Dati proprietario: dal body (wizard) con fallback sui dati del lead
        $ownerEmail = mb_strtolower($this->str($data, 'owner_email') ?? (string) $lead['email']);
        $ownerFirstName = $this->str($data, 'owner_first_name') ?? $lead['first_name'];
        $ownerLastName = $this->str($data, 'owner_last_name') ?? $lead['last_name'];
        $ownerPhone = $this->str($data, 'owner_phone', 50) ?? $lead['phone'];

        if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->error($response, 'validation', 'Email del proprietario obbligatoria per creare l\'accesso all\'area cliente.', 422);
        }

        // Dati immobile
        $title = $this->str($data, 'title') ?? sprintf('Immobile di %s %s', $ownerFirstName, $ownerLastName);
        $type = $this->str($data, 'type', 20) ?? 'appartamento';
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'altro';
        }
        $inherited = $lead['request_type'] === 'ereditato' || !empty($data['inherited']);

        try {
            $this->pdo->beginTransaction();

            // 1. Utente owner: riusa se esiste già con la stessa email
            $userStmt = $this->pdo->prepare('SELECT id, role FROM users WHERE email = :email');
            $userStmt->execute(['email' => $ownerEmail]);
            $existing = $userStmt->fetch();

            if ($existing) {
                if ($existing['role'] !== 'owner') {
                    $this->pdo->rollBack();
                    return $this->error($response, 'validation', 'L\'email indicata appartiene a un utente staff.', 422);
                }
                $ownerId = (int) $existing['id'];
                $this->pdo->prepare('UPDATE users SET is_active = true, updated_at = now() WHERE id = :id')
                    ->execute(['id' => $ownerId]);
            } else {
                $ins = $this->pdo->prepare(
                    "INSERT INTO users (agency_id, role, first_name, last_name, email, phone, is_active)
                     VALUES (1, 'owner', :fn, :ln, :email, :phone, true) RETURNING id"
                );
                $ins->execute([
                    'fn' => $ownerFirstName,
                    'ln' => $ownerLastName,
                    'email' => $ownerEmail,
                    'phone' => $ownerPhone,
                ]);
                $ownerId = (int) $ins->fetchColumn();
            }

            // 2. Immobile in valutazione
            $propIns = $this->pdo->prepare(
                "INSERT INTO properties (agency_id, owner_user_id, title, address, city, province, type, surface_sqm, rooms, status)
                 VALUES (1, :owner, :title, :address, :city, :province, :type, :sqm, :rooms, 'valutazione')
                 RETURNING id"
            );
            $propIns->execute([
                'owner' => $ownerId,
                'title' => $title,
                'address' => $this->str($data, 'address'),
                'city' => $this->str($data, 'city', 100),
                'province' => $this->str($data, 'province', 10),
                'type' => $type,
                'sqm' => isset($data['surface_sqm']) && is_numeric($data['surface_sqm']) ? (int) $data['surface_sqm'] : null,
                'rooms' => isset($data['rooms']) && is_numeric($data['rooms']) ? (int) $data['rooms'] : null,
            ]);
            $propertyId = (int) $propIns->fetchColumn();

            // Seed checklist burocrazia
            $this->stepSeeder->seedForProperty($propertyId, $inherited);

            // 3. Aggiorna lead
            $this->pdo->prepare(
                "UPDATE leads SET status = 'incarico', converted_property_id = :pid, updated_at = now() WHERE id = :id"
            )->execute(['pid' => $propertyId, 'id' => $leadId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Conversione lead fallita', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
            return $this->error($response, 'internal_error', 'Conversione non riuscita. Riprova.', 500);
        }

        // 4. Benvenuto con magic link (fuori transazione: l'email non blocca l'incarico)
        $link = $this->magicLinks->createForUser($ownerId, MagicLinkService::WELCOME_TTL_MINUTES);
        $this->mailer->send($ownerEmail, 'benvenuto_proprietario', [
            'first_name' => $ownerFirstName,
            'link' => $link,
        ], 'property', $propertyId);

        $this->logger->info('Lead convertito in incarico', ['lead_id' => $leadId, 'property_id' => $propertyId, 'owner_id' => $ownerId]);

        return $this->json($response, [
            'data' => [
                'lead_id' => $leadId,
                'owner_user_id' => $ownerId,
                'property_id' => $propertyId,
            ],
        ], 201);
    }
}
