<?php

declare(strict_types=1);

namespace App\Application\Services;

use PDO;

/**
 * Indice di interesse (%) di un immobile — calcolato, mai salvato.
 *
 * Formula (documentata anche nella UI, tooltip "come lo calcoliamo"):
 *   score = min(100, round(
 *       visite_ultimi_30gg        * 6
 *     + feedback_positivi(>=4)    * 8      (ultimi 30gg)
 *     + proposte_attive           * 25     (ricevuta/in_trattativa)
 *     + appuntamenti_futuri       * 5
 *   ))
 *
 * Trend: confronto con lo score dei 30 giorni PRECEDENTI (stessi pesi):
 *   'up' se attuale > precedente, 'down' se minore, 'flat' se uguale.
 */
final class InterestScoreService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{score:int, trend:'up'|'down'|'flat', components:array<string,int>}
     */
    public function forProperty(int $propertyId): array
    {
        $current = $this->components($propertyId, 30, 0);
        $previous = $this->components($propertyId, 60, 30);

        $score = $this->score($current);
        $prevScore = $this->score($previous);

        $trend = 'flat';
        if ($score > $prevScore) {
            $trend = 'up';
        } elseif ($score < $prevScore) {
            $trend = 'down';
        }

        return ['score' => $score, 'trend' => $trend, 'components' => $current];
    }

    /** @param array<string,int> $c */
    private function score(array $c): int
    {
        return (int) min(100, round(
            $c['visits'] * 6
            + $c['positive_feedback'] * 8
            + $c['active_proposals'] * 25
            + $c['future_appointments'] * 5
        ));
    }

    /**
     * @return array<string,int> componenti nella finestra [now-daysBack, now-daysToExclude)
     */
    private function components(int $propertyId, int $daysBack, int $daysToExclude): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM visits v
                    WHERE v.property_id = :pid
                      AND v.visited_at >= now() - make_interval(days => :back)
                      AND v.visited_at < now() - make_interval(days => :excl)) AS visits,
                (SELECT COUNT(*) FROM visits v
                    WHERE v.property_id = :pid AND v.feedback_rating >= 4
                      AND v.visited_at >= now() - make_interval(days => :back)
                      AND v.visited_at < now() - make_interval(days => :excl)) AS positive_feedback,
                (SELECT COUNT(*) FROM proposals p
                    WHERE p.property_id = :pid AND p.status IN ('ricevuta','in_trattativa')) AS active_proposals,
                (SELECT COUNT(*) FROM appointments a
                    WHERE a.property_id = :pid AND a.status = 'programmato' AND a.starts_at > now()) AS future_appointments"
        );
        $stmt->execute(['pid' => $propertyId, 'back' => $daysBack, 'excl' => $daysToExclude]);
        $row = $stmt->fetch();

        return [
            'visits' => (int) $row['visits'],
            'positive_feedback' => (int) $row['positive_feedback'],
            'active_proposals' => (int) $row['active_proposals'],
            'future_appointments' => (int) $row['future_appointments'],
        ];
    }
}
