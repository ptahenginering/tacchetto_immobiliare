<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Stats;

use App\Application\Actions\Action;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Statistiche gestionale:
 *   GET /api/admin/stats/overview        — KPI 30gg con delta % vs 30gg precedenti
 *   GET /api/admin/stats/leads-by-source — distribuzione lead per fonte
 *   GET /api/admin/stats/performance     — serie settimanale lead/visite/proposte (6 mesi)
 *   GET /api/admin/stats/property/{id}   — funnel del singolo immobile
 */
final class StatsAction extends Action
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function overview(Request $request, Response $response): Response
    {
        $kpis = [];

        $defs = [
            'new_leads' => "SELECT COUNT(*) FROM leads WHERE agency_id = 1 AND created_at >= now() - interval '%d days' AND created_at < now() - interval '%d days'",
            'handled_leads' => "SELECT COUNT(*) FROM leads WHERE agency_id = 1 AND status <> 'nuovo' AND updated_at >= now() - interval '%d days' AND updated_at < now() - interval '%d days'",
            'mandates' => "SELECT COUNT(*) FROM properties WHERE agency_id = 1 AND created_at >= now() - interval '%d days' AND created_at < now() - interval '%d days'",
            'appointments' => "SELECT COUNT(*) FROM appointments WHERE agency_id = 1 AND starts_at >= now() - interval '%d days' AND starts_at < now() - interval '%d days'",
        ];

        foreach ($defs as $key => $sqlTpl) {
            $current = (int) $this->pdo->query(sprintf($sqlTpl, 30, 0))->fetchColumn();
            $previous = (int) $this->pdo->query(sprintf($sqlTpl, 60, 30))->fetchColumn();

            $delta = null;
            if ($previous > 0) {
                $delta = round((($current - $previous) / $previous) * 100, 1);
            } elseif ($current > 0) {
                $delta = 100.0;
            }

            $kpis[$key] = ['value' => $current, 'previous' => $previous, 'delta_pct' => $delta];
        }

        return $this->json($response, ['data' => $kpis]);
    }

    public function leadsBySource(Request $request, Response $response): Response
    {
        $rows = $this->pdo->query(
            "SELECT source, COUNT(*) AS count,
                    COUNT(*) FILTER (WHERE status = 'incarico') AS converted
             FROM leads WHERE agency_id = 1
             GROUP BY source ORDER BY count DESC"
        )->fetchAll();

        return $this->json($response, ['data' => $rows]);
    }

    public function performance(Request $request, Response $response): Response
    {
        // Serie per settimana (ultimi 6 mesi), zero-filled via generate_series
        $rows = $this->pdo->query(
            "WITH weeks AS (
                SELECT date_trunc('week', s)::date AS week_start
                FROM generate_series(date_trunc('week', now() - interval '6 months'), now(), interval '1 week') s
             )
             SELECT w.week_start,
                    COALESCE(l.cnt, 0) AS leads,
                    COALESCE(v.cnt, 0) AS visits,
                    COALESCE(p.cnt, 0) AS proposals
             FROM weeks w
             LEFT JOIN (
                SELECT date_trunc('week', created_at)::date AS wk, COUNT(*) AS cnt
                FROM leads WHERE agency_id = 1 AND created_at >= now() - interval '6 months' GROUP BY 1
             ) l ON l.wk = w.week_start
             LEFT JOIN (
                SELECT date_trunc('week', visited_at)::date AS wk, COUNT(*) AS cnt
                FROM visits WHERE agency_id = 1 AND visited_at >= now() - interval '6 months' GROUP BY 1
             ) v ON v.wk = w.week_start
             LEFT JOIN (
                SELECT date_trunc('week', received_at)::date AS wk, COUNT(*) AS cnt
                FROM proposals WHERE agency_id = 1 AND received_at >= now() - interval '6 months' GROUP BY 1
             ) p ON p.wk = w.week_start
             ORDER BY w.week_start"
        )->fetchAll();

        return $this->json($response, ['data' => $rows]);
    }

    public function propertyFunnel(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        $exists = $this->pdo->prepare('SELECT id, title, status, price, created_at FROM properties WHERE id = :id AND agency_id = 1');
        $exists->execute(['id' => $id]);
        $property = $exists->fetch();
        if (!$property) {
            return $this->error($response, 'not_found', 'Immobile non trovato.', 404);
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM appointments a WHERE a.property_id = :id) AS appointments_total,
                (SELECT COUNT(*) FROM appointments a WHERE a.property_id = :id AND a.status = 'svolto') AS appointments_done,
                (SELECT COUNT(*) FROM visits v WHERE v.property_id = :id) AS visits_total,
                (SELECT COUNT(*) FROM visits v WHERE v.property_id = :id AND v.qualified = true) AS visits_qualified,
                (SELECT COUNT(*) FROM visits v WHERE v.property_id = :id AND v.feedback_rating >= 4) AS positive_feedback,
                (SELECT COUNT(*) FROM proposals p WHERE p.property_id = :id) AS proposals_total,
                (SELECT COUNT(*) FROM proposals p WHERE p.property_id = :id AND p.status IN ('ricevuta','in_trattativa')) AS proposals_active,
                (SELECT COUNT(*) FROM proposals p WHERE p.property_id = :id AND p.status = 'accettata') AS proposals_accepted,
                (SELECT MAX(p.amount) FROM proposals p WHERE p.property_id = :id) AS best_offer,
                (SELECT COUNT(*) FROM marketing_activities m WHERE m.property_id = :id) AS marketing_activities"
        );
        $stmt->execute(['id' => $id]);

        return $this->json($response, ['data' => ['property' => $property, 'funnel' => $stmt->fetch()]]);
    }
}
