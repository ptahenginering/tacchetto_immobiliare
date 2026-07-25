<?php

declare(strict_types=1);

namespace App\Application\Services;

use PDO;

/**
 * Alla creazione di un immobile genera la checklist burocrazia a partire
 * dai template (practice_step_templates). Gli step "solo eredità" vengono
 * inclusi solo se l'immobile proviene da una successione.
 */
final class PracticeStepSeeder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function seedForProperty(int $propertyId, bool $inherited = false): int
    {
        $sql = 'SELECT step_key, label, sort_order FROM practice_step_templates';
        if (!$inherited) {
            $sql .= ' WHERE only_inherited = false';
        }
        $sql .= ' ORDER BY sort_order';

        $templates = $this->pdo->query($sql)->fetchAll();

        $ins = $this->pdo->prepare(
            'INSERT INTO practice_steps (agency_id, property_id, step_key, label, status, sort_order)
             VALUES (1, :pid, :key, :label, \'da_fare\', :sort)
             ON CONFLICT (property_id, step_key) DO NOTHING'
        );

        $count = 0;
        foreach ($templates as $t) {
            $ins->execute([
                'pid' => $propertyId,
                'key' => $t['step_key'],
                'label' => $t['label'],
                'sort' => $t['sort_order'],
            ]);
            $count += $ins->rowCount();
        }

        return $count;
    }
}
