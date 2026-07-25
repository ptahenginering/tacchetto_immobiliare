<?php

declare(strict_types=1);

namespace App\Application\Actions\Customer;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Feed area cliente — SOLO dati dell'immobile del proprietario loggato
 * e SOLO elementi con visible_to_owner = true (enforcement a query).
 *
 *   GET /api/customer/visits
 *   GET /api/customer/proposals
 *   GET /api/customer/marketing
 *   GET /api/customer/practice-steps
 *   GET /api/customer/timeline
 */
final class CustomerFeedAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function visits(Request $request, Response $response): Response
    {
        $pid = $this->propertyIdForOwner($request);
        if ($pid === null) {
            return $this->noProperty($response);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, visited_at, visitor_label, qualified, feedback_text, feedback_rating
             FROM visits
             WHERE property_id = :pid AND visible_to_owner = true
             ORDER BY visited_at DESC'
        );
        $stmt->execute(['pid' => $pid]);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }

    public function proposals(Request $request, Response $response): Response
    {
        $pid = $this->propertyIdForOwner($request);
        if ($pid === null) {
            return $this->noProperty($response);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, amount, status, received_at, created_at
             FROM proposals
             WHERE property_id = :pid AND visible_to_owner = true
             ORDER BY received_at DESC'
        );
        $stmt->execute(['pid' => $pid]);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }

    public function marketing(Request $request, Response $response): Response
    {
        $pid = $this->propertyIdForOwner($request);
        if ($pid === null) {
            return $this->noProperty($response);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, channel, activity_type, title, url, published_at, stats
             FROM marketing_activities
             WHERE property_id = :pid AND visible_to_owner = true
             ORDER BY published_at DESC NULLS LAST, created_at DESC'
        );
        $stmt->execute(['pid' => $pid]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['stats'] = json_decode((string) $row['stats'], true) ?: (object) [];
        }

        return $this->json($response, ['data' => $rows]);
    }

    public function practiceSteps(Request $request, Response $response): Response
    {
        $pid = $this->propertyIdForOwner($request);
        if ($pid === null) {
            return $this->noProperty($response);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, step_key, label, status, sort_order, completed_at
             FROM practice_steps
             WHERE property_id = :pid AND visible_to_owner = true
             ORDER BY sort_order'
        );
        $stmt->execute(['pid' => $pid]);
        $steps = $stmt->fetchAll();

        $total = count($steps);
        $done = count(array_filter($steps, fn ($s) => $s['status'] === 'completato'));

        return $this->json($response, [
            'data' => $steps,
            'meta' => [
                'total' => $total,
                'completed' => $done,
                'progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
            ],
        ]);
    }

    public function timeline(Request $request, Response $response): Response
    {
        $pid = $this->propertyIdForOwner($request);
        if ($pid === null) {
            return $this->noProperty($response);
        }

        // Feed cronologico unificato di tutti gli eventi visibili
        $stmt = $this->pdo->prepare(
            "SELECT * FROM (
                SELECT 'visita' AS event_type, v.id, v.visited_at AS happened_at,
                       v.visitor_label AS title,
                       COALESCE(v.feedback_text, '') AS detail,
                       v.feedback_rating AS rating
                FROM visits v
                WHERE v.property_id = :pid AND v.visible_to_owner = true

                UNION ALL

                SELECT 'proposta', p.id, p.received_at,
                       'Proposta ' || p.status,
                       '', NULL
                FROM proposals p
                WHERE p.property_id = :pid AND p.visible_to_owner = true

                UNION ALL

                SELECT 'promozione', m.id, COALESCE(m.published_at, m.created_at),
                       m.title,
                       m.channel, NULL
                FROM marketing_activities m
                WHERE m.property_id = :pid AND m.visible_to_owner = true

                UNION ALL

                SELECT 'pratica', ps.id, ps.completed_at,
                       ps.label,
                       'completato', NULL
                FROM practice_steps ps
                WHERE ps.property_id = :pid AND ps.visible_to_owner = true AND ps.completed_at IS NOT NULL

                UNION ALL

                SELECT 'appuntamento', a.id, a.starts_at,
                       'Appuntamento: ' || a.type,
                       a.status, NULL
                FROM appointments a
                WHERE a.property_id = :pid AND a.status <> 'annullato'
             ) t
             WHERE t.happened_at IS NOT NULL
             ORDER BY t.happened_at DESC
             LIMIT 60"
        );
        $stmt->execute(['pid' => $pid]);

        return $this->json($response, ['data' => $stmt->fetchAll()]);
    }

    /** Immobile del proprietario loggato (owner-scoping a livello di query). */
    private function propertyIdForOwner(Request $request): ?int
    {
        $uid = (int) $request->getAttribute('auth_uid');
        $stmt = $this->pdo->prepare(
            'SELECT id FROM properties WHERE owner_user_id = :uid AND agency_id = 1 ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $uid]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    private function noProperty(Response $response): Response
    {
        return $this->error($response, 'no_property', 'Nessun immobile associato al tuo profilo.', 404);
    }
}
