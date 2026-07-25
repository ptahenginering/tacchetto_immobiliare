<?php

declare(strict_types=1);

/**
 * Seed dati demo RT CASA LIVE — per la presentazione a Roberto.
 *
 * Crea: 1 proprietario demo con immobile in vendita a Silea, 12 appuntamenti,
 * 18 visite con feedback realistici, 2 proposte, 6 attività marketing con stats,
 * step burocrazia a metà, 15 lead in vari stati distribuiti su 8 settimane.
 *
 * Uso: php bin/seed-demo.php
 * Idempotente: se il proprietario demo esiste già, esce senza duplicare.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\Connection;

$envDir = __DIR__ . '/../config';
if (is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->load();
}

$pdo = Connection::get();

const DEMO_EMAIL = 'demo.proprietario@rtimmobiliare.it';

$exists = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$exists->execute(['email' => DEMO_EMAIL]);
if ($exists->fetch()) {
    echo "Seed demo già presente (" . DEMO_EMAIL . "), esco.\n";
    exit(0);
}

echo "== Seed dati demo ==\n";
$pdo->beginTransaction();

try {
    // --- Proprietario demo ---
    $stmt = $pdo->prepare(
        "INSERT INTO users (agency_id, role, first_name, last_name, email, phone, is_active)
         VALUES (1, 'owner', 'Marco', 'Bortolin', :email, '+39 340 1122334', true) RETURNING id"
    );
    $stmt->execute(['email' => DEMO_EMAIL]);
    $ownerId = (int) $stmt->fetchColumn();
    echo "Proprietario demo #{$ownerId}\n";

    // --- Immobile in vendita a Silea ---
    $stmt = $pdo->prepare(
        "INSERT INTO properties (agency_id, owner_user_id, title, address, city, province, type, surface_sqm, rooms,
                                 price, status, description, mandate_start, mandate_end)
         VALUES (1, :owner, 'Appartamento tricamere con giardino', 'Via Roma 42', 'Silea', 'TV', 'appartamento',
                 125, 5, 265000.00, 'in_vendita',
                 'Luminoso tricamere al piano terra con giardino privato di 180 mq, doppio garage e ottima esposizione. Contesto residenziale tranquillo a due passi dal centro di Silea.',
                 now()::date - 70, now()::date + 110)
         RETURNING id"
    );
    $stmt->execute(['owner' => $ownerId]);
    $propertyId = (int) $stmt->fetchColumn();
    echo "Immobile demo #{$propertyId}\n";

    // --- Step burocrazia (a metà) ---
    $steps = $pdo->query('SELECT step_key, label, sort_order FROM practice_step_templates WHERE only_inherited = false ORDER BY sort_order')->fetchAll();
    $ins = $pdo->prepare(
        "INSERT INTO practice_steps (agency_id, property_id, step_key, label, status, sort_order, completed_at)
         VALUES (1, :pid, :key, :label, :status, :sort, :completed)"
    );
    foreach ($steps as $i => $s) {
        $status = $i < 3 ? 'completato' : ($i === 3 ? 'in_corso' : 'da_fare');
        $ins->execute([
            'pid' => $propertyId,
            'key' => $s['step_key'],
            'label' => $s['label'],
            'status' => $status,
            'sort' => $s['sort_order'],
            'completed' => $status === 'completato' ? date('Y-m-d H:i:sP', strtotime('-' . (40 - $i * 9) . ' days')) : null,
        ]);
    }
    echo 'Step burocrazia: ' . count($steps) . " (3 completati, 1 in corso)\n";

    // --- 12 appuntamenti (10 passati svolti, 2 futuri) ---
    $apptIns = $pdo->prepare(
        "INSERT INTO appointments (agency_id, property_id, type, starts_at, ends_at, contact_name, status)
         VALUES (1, :pid, :type, :starts, :ends, :contact, :status) RETURNING id"
    );
    $contacts = ['Famiglia Rigo', 'Sig.ra Pavan', 'Coppia Zanette', 'Sig. Furlan', 'Famiglia De Marchi', 'Sig.ra Callegari', 'Coppia Bianchin', 'Sig. Tonon', 'Famiglia Gobbo', 'Sig.ra Dal Pos', 'Coppia Moretto', 'Sig. Perin'];
    $appointmentIds = [];
    for ($i = 0; $i < 12; $i++) {
        $isFuture = $i >= 10;
        $daysOffset = $isFuture ? ($i - 9) * 2 : -(52 - $i * 5); // passati distribuiti su ~8 settimane
        $start = strtotime(($daysOffset >= 0 ? '+' : '') . $daysOffset . ' days 10:00') + ($i % 4) * 5400;
        $apptIns->execute([
            'pid' => $propertyId,
            'type' => $i === 0 ? 'valutazione' : 'visita',
            'starts' => date('Y-m-d H:i:sP', $start),
            'ends' => date('Y-m-d H:i:sP', $start + 3600),
            'contact' => $contacts[$i],
            'status' => $isFuture ? 'programmato' : 'svolto',
        ]);
        $appointmentIds[] = (int) $apptIns->fetchColumn();
    }
    echo "12 appuntamenti (2 futuri)\n";

    // --- 18 visite con feedback realistici ---
    $feedbacks = [
        ['Coppia giovane, prima casa', 5, 'Casa bellissima e curata, il giardino è esattamente ciò che cercavamo. Ci stiamo confrontando con la banca per il mutuo.', true],
        ['Famiglia con due bambini', 4, 'Molto luminosa e la zona è perfetta per la scuola. La cucina andrebbe rinnovata ma il prezzo lo riflette.', true],
        ['Signora, cerca per il figlio', 3, 'Bella soluzione ma cercavamo qualcosa con una camera in più.', true],
        ['Coppia, seconda visita', 5, 'Tornati per rivedere gli spazi esterni: il giardino al pomeriggio è molto vivibile. Valutiamo una proposta.', true],
        ['Sig. sui 50, investimento', 4, 'Buono stato generale, mi interessa la resa da affitto. Chiederò una valutazione al mio commercialista.', true],
        ['Famiglia, trasferimento da Treviso', 4, 'Zona tranquilla e ben servita. Il doppio garage è un plus raro. Vogliono rivederla col padre.', true],
        ['Coppia giovane', 2, 'Preferiscono una soluzione più moderna, classe energetica più alta.', true],
        ['Sig.ra con agente di fiducia', 4, 'Impressione molto positiva, apprezzata la conformità documentale già pronta.', true],
        ['Famiglia, prima casa', 5, 'Innamorati del giardino. Devono vendere il loro appartamento prima di procedere.', true],
        ['Coppia di pensionati', 3, 'Cercano tutto su un piano: il piano terra va bene ma i gradini d\'ingresso li frenano.', true],
        ['Giovane professionista', 4, 'Ottima per smart working, stanza studio perfetta. Valuta anche un\'altra zona.', true],
        ['Famiglia con cane', 5, 'Il giardino recintato è ideale. Molto interessati, chiedono tempi di rogito.', true],
        ['Coppia al primo acquisto', 3, 'Piaciuta ma il budget è più basso, attendono risposta della banca.', true],
        ['Sig. straniero, ricollocazione', 4, 'Apprezzata la vicinanza alle scuole internazionali di Treviso.', true],
        ['Curiosi di zona', 1, 'Visita conoscitiva, non realmente motivati.', false],
        ['Famiglia De Marchi (ritorno)', 5, 'Terza visita con i genitori: tutti convinti, stanno preparando una proposta.', true],
        ['Coppia Zanette (ritorno)', 4, 'Ricontrollate le misure per i mobili: tutto ok.', true],
        ['Sig.ra Pavan (ritorno)', 4, 'Rivista la casa al mattino per la luce: confermata l\'ottima impressione.', true],
    ];
    $visitIns = $pdo->prepare(
        "INSERT INTO visits (agency_id, property_id, appointment_id, visited_at, visitor_label, qualified,
                             feedback_text, feedback_rating, visible_to_owner)
         VALUES (1, :pid, :aid, :visited, :label, :qualified, :text, :rating, true)"
    );
    foreach ($feedbacks as $i => [$label, $rating, $text, $qualified]) {
        $visitIns->execute([
            'pid' => $propertyId,
            'aid' => $appointmentIds[$i % 10] ?? null,
            'visited' => date('Y-m-d H:i:sP', strtotime('-' . (54 - $i * 3) . ' days 11:00')),
            'label' => $label,
            'qualified' => $qualified ? 'true' : 'false',
            'text' => $text,
            'rating' => $rating,
        ]);
    }
    echo "18 visite con feedback\n";

    // --- 2 proposte ---
    $propIns = $pdo->prepare(
        "INSERT INTO proposals (agency_id, property_id, amount, status, received_at, notes, visible_to_owner)
         VALUES (1, :pid, :amount, :status, :received, :notes, true)"
    );
    $propIns->execute([
        'pid' => $propertyId,
        'amount' => '245000.00',
        'status' => 'rifiutata',
        'received' => date('Y-m-d H:i:sP', strtotime('-25 days')),
        'notes' => 'Proposta della coppia al primo acquisto: troppo bassa, rifiutata dopo confronto col proprietario.',
    ]);
    $propIns->execute([
        'pid' => $propertyId,
        'amount' => '255000.00',
        'status' => 'in_trattativa',
        'received' => date('Y-m-d H:i:sP', strtotime('-4 days')),
        'notes' => 'Famiglia De Marchi: proposta seria con mutuo pre-approvato. In trattativa sul prezzo.',
    ]);
    echo "2 proposte\n";

    // --- 6 attività marketing ---
    $mkt = [
        ['immobiliare_it', 'pubblicazione', 'Annuncio Premium Immobiliare.it', 'https://www.immobiliare.it/annunci/demo', -60, ['views' => 3421, 'contacts' => 14]],
        ['idealista', 'pubblicazione', 'Annuncio Idealista', 'https://www.idealista.it/immobile/demo', -58, ['views' => 1876, 'contacts' => 6]],
        ['portale_tecnocasa', 'pubblicazione', 'Scheda sul portale Tecnocasa', null, -60, ['views' => 954, 'contacts' => 4]],
        ['facebook', 'campagna', 'Campagna Facebook zona Silea/Treviso', null, -35, ['views' => 18240, 'clicks' => 312, 'contacts' => 9]],
        ['instagram', 'post', 'Reel presentazione con tour del giardino', null, -20, ['views' => 5620, 'contacts' => 3]],
        ['vetrina', 'altro', 'Esposizione in vetrina agenzia + QR code', null, -60, ['contacts' => 2]],
    ];
    $mktIns = $pdo->prepare(
        "INSERT INTO marketing_activities (agency_id, property_id, channel, activity_type, title, url, published_at, stats, visible_to_owner)
         VALUES (1, :pid, :channel, :type, :title, :url, :published, (:stats)::jsonb, true)"
    );
    foreach ($mkt as [$channel, $type, $title, $url, $days, $stats]) {
        $mktIns->execute([
            'pid' => $propertyId,
            'channel' => $channel,
            'type' => $type,
            'title' => $title,
            'url' => $url,
            'published' => date('Y-m-d H:i:sP', strtotime("{$days} days")),
            'stats' => json_encode($stats),
        ]);
    }
    echo "6 attività marketing\n";

    // --- 15 lead su 8 settimane ---
    $leads = [
        ['Giulia', 'Sartori', 'giulia.sartori@example.it', '+39 347 1112223', 'vendere', 'sito', 'incarico', 'Vorrei vendere il mio bilocale a Treviso, zona stazione.'],
        ['Andrea', 'Vettor', 'andrea.vettor@example.it', '+39 340 2223334', 'vendere', 'sito', 'appuntamento', 'Casa singola a Casier, chiedo una valutazione.'],
        ['Elena', 'Businaro', 'elena.businaro@example.it', null, 'ereditato', 'sito', 'contattato', 'Ho ereditato un appartamento con mia sorella, non sappiamo da dove partire.'],
        ['Luca', 'Favaro', null, '+39 333 4445556', 'vendere', 'qr', 'contattato', 'Visto il QR in vetrina, vorrei informazioni.'],
        ['Martina', 'Piovesan', 'martina.piovesan@example.it', '+39 349 5556667', 'vendere', 'social', 'nuovo', 'Vi ho trovato su Instagram. Vorrei vendere entro l\'estate.'],
        ['Paolo', 'Girardi', 'paolo.girardi@example.it', '+39 335 6667778', 'altro', 'referral', 'appuntamento', 'Mi manda la famiglia Bortolin. Ho un rustico da valutare.'],
        ['Francesca', 'Marcon', 'francesca.marcon@example.it', null, 'vendere', 'sito', 'nuovo', 'Appartamento a Paese, 3 camere, vorrei capire il valore.'],
        ['Davide', 'Bettin', 'davide.bettin@example.it', '+39 342 7778889', 'ereditato', 'sito', 'perso', 'Successione da gestire.'],
        ['Chiara', 'Pillon', 'chiara.pillon@example.it', '+39 346 8889990', 'vendere', 'social', 'contattato', 'Ho visto i vostri video, complimenti. Villetta a Carbonera.'],
        ['Stefano', 'Rossetto', null, '+39 338 9990001', 'vendere', 'qr', 'nuovo', null],
        ['Valentina', 'Cattarin', 'valentina.cattarin@example.it', '+39 345 0001112', 'vendere', 'sito', 'contattato', 'Trasferimento per lavoro, devo vendere in tempi brevi.'],
        ['Roberto', 'Feltrin', 'roberto.feltrin@example.it', null, 'altro', 'sito', 'nuovo', 'Info su permuta.'],
        ['Silvia', 'Dal Bo', 'silvia.dalbo@example.it', '+39 331 1112223', 'vendere', 'referral', 'appuntamento', 'Consigliata da un vostro cliente. Appartamento a Silea.'],
        ['Matteo', 'Gasparini', 'matteo.gasparini@example.it', '+39 339 2223334', 'ereditato', 'sito', 'nuovo', 'Casa dei nonni a Roncade, siamo tre eredi.'],
        ['Anna', 'Visentin', 'anna.visentin@example.it', '+39 347 3334445', 'vendere', 'social', 'nuovo', 'Miniappartamento affittato, vorrei venderlo.'],
    ];
    $leadIns = $pdo->prepare(
        "INSERT INTO leads (agency_id, first_name, last_name, email, phone, request_type, message, source, status, created_at, updated_at)
         VALUES (1, :fn, :ln, :email, :phone, :rt, :msg, :src, :status, :created, :created)"
    );
    foreach ($leads as $i => [$fn, $ln, $email, $phone, $rt, $src, $status, $msg]) {
        $leadIns->execute([
            'fn' => $fn,
            'ln' => $ln,
            'email' => $email,
            'phone' => $phone,
            'rt' => $rt,
            'msg' => $msg,
            'src' => $src,
            'status' => $status,
            'created' => date('Y-m-d H:i:sP', strtotime('-' . (56 - $i * 4) . ' days ' . (9 + $i % 9) . ':00')),
        ]);
    }
    echo "15 lead su 8 settimane\n";

    $pdo->commit();
    echo "Seed demo completato ✔\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERRORE: {$e->getMessage()}\n");
    exit(1);
}
