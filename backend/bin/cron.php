<?php

declare(strict_types=1);

/**
 * Scheduler RT CASA LIVE — da crontab ogni 15 minuti:
 *   *\/15 * * * * php /path/backend/bin/cron.php >> /path/backend/logs/cron.log 2>&1
 *
 * Job (tutti idempotenti, tabella cron_runs + flag per-riga):
 *   1. Promemoria appuntamenti del giorno dopo (una sola email per appuntamento)
 *   2. Report settimanale ai proprietari con immobile in_vendita/in_trattativa (lunedì ≥ 8:00)
 *   3. Pulizia: magic link scaduti, login_attempts e request_throttle vecchi
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Security\LoginRateLimiter;
use App\Application\Security\ThrottleService;
use App\Application\Services\MagicLinkService;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logger;
use App\Infrastructure\Mail\BrevoService;
use App\Infrastructure\Mail\EmailTemplates;
use App\Infrastructure\Mail\MailService;

$envDir = __DIR__ . '/../config';
if (is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$logger = new Logger(__DIR__ . '/../logs/app.log');

try {
    $pdo = Connection::get();
} catch (Throwable $e) {
    fwrite(STDERR, "[cron] connessione fallita: {$e->getMessage()}\n");
    exit(1);
}

$appUrl = $_ENV['APP_URL'] ?? 'https://tacchettoimmobiliare.it';
$brevoKey = $_ENV['BREVO_API_KEY'] ?? '';
$brevo = $brevoKey !== ''
    ? new BrevoService($brevoKey, $_ENV['MAIL_FROM'] ?? 'info@rtimmobiliare.it', $_ENV['MAIL_FROM_NAME'] ?? 'RT CASA LIVE')
    : null;
$mailer = new MailService($pdo, new EmailTemplates($appUrl), $logger, $brevo);

/** Prova a "prendere" un job: true se questo processo lo deve eseguire. */
$claimJob = function (string $jobKey) use ($pdo): bool {
    $stmt = $pdo->prepare('INSERT INTO cron_runs (job_key) VALUES (:k) ON CONFLICT (job_key) DO NOTHING');
    $stmt->execute(['k' => $jobKey]);
    return $stmt->rowCount() === 1;
};

echo '[cron] ' . date('Y-m-d H:i:s') . " avvio\n";

// ------------------------------------------------------------------
// 1. Promemoria appuntamenti di domani
// ------------------------------------------------------------------
$appts = $pdo->query(
    "SELECT a.id, a.type, a.starts_at, p.address, p.city,
            u.first_name AS owner_first_name, u.email AS owner_email
     FROM appointments a
     JOIN properties p ON p.id = a.property_id
     JOIN users u ON u.id = p.owner_user_id
     WHERE a.status = 'programmato'
       AND a.reminder_sent_at IS NULL
       AND a.starts_at::date = (now() + interval '1 day')::date
       AND u.email IS NOT NULL"
)->fetchAll();

foreach ($appts as $a) {
    $when = date('d/m/Y · H:i', strtotime((string) $a['starts_at']));
    $address = trim(($a['address'] ?? '') . ' ' . ($a['city'] ?? ''));

    $sent = $mailer->send((string) $a['owner_email'], 'promemoria_appuntamento', [
        'first_name' => $a['owner_first_name'],
        'when' => $when,
        'type' => $a['type'],
        'address' => $address,
    ], 'appointment', (int) $a['id']);

    // Marca comunque come processato per evitare tempeste di retry ogni 15 min
    $pdo->prepare('UPDATE appointments SET reminder_sent_at = now(), updated_at = now() WHERE id = :id')
        ->execute(['id' => $a['id']]);

    echo "[cron] promemoria appuntamento #{$a['id']}: " . ($sent ? 'inviato' : 'non inviato (vedi email_log)') . "\n";
}

// ------------------------------------------------------------------
// 2. Report settimanale (lunedì dalle 8:00)
// ------------------------------------------------------------------
if ((int) date('N') === 1 && (int) date('G') >= 8) {
    $weekKey = 'weekly_report_' . date('o_W');
    if ($claimJob($weekKey)) {
        $owners = $pdo->query(
            "SELECT DISTINCT u.id, u.first_name, u.email, p.id AS property_id
             FROM users u
             JOIN properties p ON p.owner_user_id = u.id
             WHERE u.role = 'owner' AND u.is_active = true AND u.email IS NOT NULL
               AND p.status IN ('in_vendita', 'in_trattativa')"
        )->fetchAll();

        foreach ($owners as $o) {
            $kpi = $pdo->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM visits v WHERE v.property_id = :pid AND v.visible_to_owner = true
                        AND v.visited_at > now() - interval '7 days') AS visits_7d,
                    (SELECT COUNT(*) FROM appointments a WHERE a.property_id = :pid
                        AND a.status = 'programmato' AND a.starts_at > now()) AS appointments_next,
                    (SELECT COUNT(*) FROM proposals pr WHERE pr.property_id = :pid AND pr.visible_to_owner = true
                        AND pr.status IN ('ricevuta', 'in_trattativa')) AS proposals_active"
            );
            $kpi->execute(['pid' => $o['property_id']]);
            $stats = $kpi->fetch();

            $mailer->send((string) $o['email'], 'report_settimanale', [
                'first_name' => $o['first_name'],
                'visits_7d' => (int) $stats['visits_7d'],
                'appointments_next' => (int) $stats['appointments_next'],
                'proposals_active' => (int) $stats['proposals_active'],
            ], 'property', (int) $o['property_id']);

            echo "[cron] report settimanale → owner #{$o['id']}\n";
        }
    }
}

// ------------------------------------------------------------------
// 3. Pulizie giornaliere
// ------------------------------------------------------------------
$purgeKey = 'purge_' . date('Y-m-d');
if ($claimJob($purgeKey)) {
    $magic = (new MagicLinkService($pdo))->purgeExpired();
    $logins = (new LoginRateLimiter($pdo))->purgeOld();
    $throttle = (new ThrottleService($pdo))->purgeOld();
    // cron_runs più vecchi di 90 giorni
    $pdo->exec("DELETE FROM cron_runs WHERE ran_at < now() - interval '90 days'");
    echo "[cron] pulizia: {$magic} magic link, {$logins} login_attempts, {$throttle} throttle\n";
}

echo '[cron] ' . date('Y-m-d H:i:s') . " fine\n";
