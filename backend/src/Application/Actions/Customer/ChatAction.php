<?php

declare(strict_types=1);

namespace App\Application\Actions\Customer;

use App\Application\Actions\Action;
use App\Application\Security\ThrottleService;
use App\Infrastructure\Ai\AnthropicService;
use App\Infrastructure\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * POST /api/customer/chat — assistente AI dell'area cliente.
 *
 * Riceve nel contesto i dati REALI dell'immobile del cliente (stato, KPI,
 * prossimi step) e risponde solo su temi legati alla vendita. Persistenza in
 * chat_sessions/chat_messages. Senza ANTHROPIC_API_KEY: risposta gentile di
 * indisponibilità (mai crash).
 */
final class ChatAction extends Action
{
    private const UNAVAILABLE_MESSAGE = 'L\'assistente digitale è momentaneamente non disponibile. Per qualsiasi domanda puoi contattare direttamente Roberto al +39 345 7771822 o via email info@rtimmobiliare.it.';
    private const MAX_HISTORY = 10;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AnthropicService $anthropic,
        private readonly ThrottleService $throttle,
        private readonly Logger $logger,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $uid = (int) $request->getAttribute('auth_uid');
        $data = $this->body($request);

        $message = $this->str($data, 'message', 2000);
        if ($message === null) {
            return $this->error($response, 'validation', 'Messaggio vuoto.', 422);
        }

        if ($this->throttle->tooManyRequests('chatbot', 'uid:' . $uid, 30, 15)) {
            return $this->error($response, 'rate_limited', 'Hai inviato molti messaggi: attendi qualche minuto.', 429);
        }

        // --- Contesto reale dell'immobile ---
        $prop = $this->pdo->prepare(
            "SELECT p.id, p.title, p.address, p.city, p.type, p.surface_sqm, p.rooms, p.status, p.price,
                    u.first_name AS owner_first_name
             FROM properties p JOIN users u ON u.id = p.owner_user_id
             WHERE p.owner_user_id = :uid AND p.agency_id = 1
             ORDER BY p.created_at DESC LIMIT 1"
        );
        $prop->execute(['uid' => $uid]);
        $property = $prop->fetch();

        // --- Sessione ---
        $sessionId = isset($data['session_id']) ? (int) $data['session_id'] : 0;
        if ($sessionId > 0) {
            $check = $this->pdo->prepare('SELECT id FROM chat_sessions WHERE id = :id AND user_id = :uid');
            $check->execute(['id' => $sessionId, 'uid' => $uid]);
            if (!$check->fetch()) {
                $sessionId = 0;
            }
        }
        if ($sessionId === 0) {
            $ins = $this->pdo->prepare(
                'INSERT INTO chat_sessions (agency_id, user_id, property_id) VALUES (1, :uid, :pid) RETURNING id'
            );
            $ins->execute(['uid' => $uid, 'pid' => $property['id'] ?? null]);
            $sessionId = (int) $ins->fetchColumn();
        }

        // Salva messaggio utente
        $this->pdo->prepare(
            "INSERT INTO chat_messages (agency_id, session_id, role, content) VALUES (1, :sid, 'user', :content)"
        )->execute(['sid' => $sessionId, 'content' => $message]);

        // --- Senza chiave API: risposta gentile ---
        if (!$this->anthropic->isConfigured()) {
            $this->saveAssistantMessage($sessionId, self::UNAVAILABLE_MESSAGE, 0);
            $this->logger->warning('Chatbot senza ANTHROPIC_API_KEY: feature disattivata');
            return $this->json($response, [
                'data' => ['session_id' => $sessionId, 'reply' => self::UNAVAILABLE_MESSAGE, 'available' => false],
            ]);
        }

        // --- Storico conversazione (ultimi N) ---
        $historyStmt = $this->pdo->prepare(
            'SELECT role, content FROM (
                SELECT role, content, id FROM chat_messages
                WHERE session_id = :sid ORDER BY id DESC LIMIT ' . self::MAX_HISTORY . '
             ) t ORDER BY id'
        );
        $historyStmt->execute(['sid' => $sessionId]);
        $messages = array_map(
            fn ($m) => ['role' => $m['role'], 'content' => $m['content']],
            $historyStmt->fetchAll()
        );

        try {
            $result = $this->anthropic->chat($this->systemPrompt($property, $uid), $messages);
            $reply = trim($result['text']) !== '' ? trim($result['text']) : self::UNAVAILABLE_MESSAGE;
            $this->saveAssistantMessage($sessionId, $reply, $result['tokens']);

            return $this->json($response, [
                'data' => ['session_id' => $sessionId, 'reply' => $reply, 'available' => true],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Chatbot: chiamata Anthropic fallita', ['error' => $e->getMessage()]);
            $this->saveAssistantMessage($sessionId, self::UNAVAILABLE_MESSAGE, 0);

            return $this->json($response, [
                'data' => ['session_id' => $sessionId, 'reply' => self::UNAVAILABLE_MESSAGE, 'available' => false],
            ]);
        }
    }

    private function saveAssistantMessage(int $sessionId, string $content, int $tokens): void
    {
        $this->pdo->prepare(
            "INSERT INTO chat_messages (agency_id, session_id, role, content, tokens_used)
             VALUES (1, :sid, 'assistant', :content, :tokens)"
        )->execute(['sid' => $sessionId, 'content' => $content, 'tokens' => $tokens]);
    }

    /** @param array<string,mixed>|false $property */
    private function systemPrompt(array|false $property, int $uid): string
    {
        $context = 'Il cliente non ha ancora un immobile associato.';

        if ($property) {
            $kpi = $this->pdo->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM visits v WHERE v.property_id = :pid AND v.visible_to_owner = true
                        AND v.visited_at >= now() - interval '30 days') AS visits_30d,
                    (SELECT COUNT(*) FROM proposals p WHERE p.property_id = :pid AND p.visible_to_owner = true
                        AND p.status IN ('ricevuta','in_trattativa')) AS proposals_active,
                    (SELECT COUNT(*) FROM appointments a WHERE a.property_id = :pid
                        AND a.status = 'programmato' AND a.starts_at > now()) AS upcoming_appointments"
            );
            $kpi->execute(['pid' => $property['id']]);
            $k = $kpi->fetch();

            $steps = $this->pdo->prepare(
                "SELECT label, status FROM practice_steps
                 WHERE property_id = :pid AND visible_to_owner = true AND status <> 'completato'
                 ORDER BY sort_order LIMIT 3"
            );
            $steps->execute(['pid' => $property['id']]);
            $nextSteps = implode('; ', array_map(
                fn ($s) => $s['label'] . ' (' . $s['status'] . ')',
                $steps->fetchAll()
            )) ?: 'nessuno in sospeso';

            $context = sprintf(
                "Immobile del cliente: %s — %s, %s (%s, %s mq, %s locali). Stato vendita: %s.\n" .
                'Ultimi 30 giorni: %d visite, %d proposte attive, %d appuntamenti in programma.' . "\n" .
                'Prossimi step burocratici: %s.',
                $property['title'],
                $property['address'] ?? 'indirizzo n.d.',
                $property['city'] ?? '',
                $property['type'],
                $property['surface_sqm'] ?? 'n.d.',
                $property['rooms'] ?? 'n.d.',
                $property['status'],
                (int) $k['visits_30d'],
                (int) $k['proposals_active'],
                (int) $k['upcoming_appointments'],
                $nextSteps
            );
        }

        return <<<PROMPT
Sei l'assistente digitale di RT CASA LIVE, la piattaforma di Roberto Tacchetto, Real Estate Advisor (network Tecnocasa, Treviso e provincia). Motto: "Trasparenza. Controllo. Risultati."

Parli SOLO in italiano, con tono professionale, rassicurante e concreto. Ti rivolgi al proprietario che sta vendendo casa con Roberto.

DATI REALI DEL CLIENTE (usali per rispondere con precisione):
{$context}

REGOLE:
- Rispondi solo su temi legati alla vendita dell'immobile del cliente: andamento, visite, proposte, promozione, step burocratici, come funziona la piattaforma.
- Non inventare mai dati: usa solo quelli forniti sopra. Se un'informazione non è disponibile, dillo e invita a guardare nell'app o a chiedere a Roberto.
- Per richieste fuori ambito, consulenza legale, fiscale o notarile: invita gentilmente a contattare direttamente Roberto al +39 345 7771822.
- Non rivelare mai dati di altri clienti o dei visitatori.
- Risposte brevi e chiare (max ~150 parole), formattate in paragrafi semplici.
PROMPT;
    }
}
