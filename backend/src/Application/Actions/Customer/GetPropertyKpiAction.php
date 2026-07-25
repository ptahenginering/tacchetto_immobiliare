<?php

declare(strict_types=1);

namespace App\Application\Actions\Customer;

use App\Application\Actions\Action;
use App\Application\Services\InterestScoreService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/customer/property/kpi — KPI dashboard del proprietario:
 * appuntamenti 30gg, visite 30gg, proposte in trattativa, interest score
 * con trend e spiegazione, serie visite per settimana (ultime 8).
 */
final class GetPropertyKpiAction extends Action
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly InterestScoreService $interestScore,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $uid = (int) $request->getAttribute('auth_uid');

        $prop = $this->pdo->prepare(
            'SELECT id FROM properties WHERE owner_user_id = :uid AND agency_id = 1 ORDER BY created_at DESC LIMIT 1'
        );
        $prop->execute(['uid' => $uid]);
        $property = $prop->fetch();

        if (!$property) {
            return $this->error($response, 'no_property', 'Nessun immobile associato al tuo profilo.', 404);
        }
        $pid = (int) $property['id'];

        $kpi = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM appointments a WHERE a.property_id = :pid
                    AND a.starts_at >= now() - interval '30 days') AS appointments_30d,
                (SELECT COUNT(*) FROM appointments a WHERE a.property_id = :pid
                    AND a.status = 'programmato' AND a.starts_at > now()) AS appointments_upcoming,
                (SELECT COUNT(*) FROM visits v WHERE v.property_id = :pid AND v.visible_to_owner = true
                    AND v.visited_at >= now() - interval '30 days') AS visits_30d,
                (SELECT COUNT(*) FROM proposals p WHERE p.property_id = :pid AND p.visible_to_owner = true
                    AND p.status IN ('ricevuta','in_trattativa')) AS proposals_active"
        );
        $kpi->execute(['pid' => $pid]);
        $kpis = $kpi->fetch();

        $score = $this->interestScore->forProperty($pid);

        // Serie visite per settimana — ultime 8 settimane, zero-filled
        $series = $this->pdo->prepare(
            "WITH weeks AS (
                SELECT date_trunc('week', s)::date AS week_start
                FROM generate_series(date_trunc('week', now() - interval '7 weeks'), now(), interval '1 week') s
             )
             SELECT w.week_start, COALESCE(v.cnt, 0) AS visits
             FROM weeks w
             LEFT JOIN (
                SELECT date_trunc('week', visited_at)::date AS wk, COUNT(*) AS cnt
                FROM visits
                WHERE property_id = :pid AND visible_to_owner = true
                  AND visited_at >= now() - interval '8 weeks'
                GROUP BY 1
             ) v ON v.wk = w.week_start
             ORDER BY w.week_start"
        );
        $series->execute(['pid' => $pid]);

        return $this->json($response, [
            'data' => [
                'appointments_30d' => (int) $kpis['appointments_30d'],
                'appointments_upcoming' => (int) $kpis['appointments_upcoming'],
                'visits_30d' => (int) $kpis['visits_30d'],
                'proposals_active' => (int) $kpis['proposals_active'],
                'interest' => [
                    'score' => $score['score'],
                    'trend' => $score['trend'],
                    'explanation' => 'Calcolato su visite degli ultimi 30 giorni, riscontri positivi, proposte attive e appuntamenti in programma.',
                ],
                'visits_series' => $series->fetchAll(),
            ],
        ]);
    }
}
