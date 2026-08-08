<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin\Ai;

use App\Application\Actions\Action;
use App\Infrastructure\Ai\AnthropicService;
use App\Infrastructure\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * POST /api/admin/ai/generate — genera testi marketing con Claude.
 * Body: {property_id, kind: annuncio|post_social|descrizione|email, extra_notes?}
 * Output salvato in ai_generations. Senza chiave API → 503 con messaggio chiaro.
 */
final class GenerateAiAction extends Action
{
    private const KINDS = ['annuncio', 'post_social', 'descrizione', 'email', 'scheda'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly AnthropicService $anthropic,
        private readonly Logger $logger,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $this->body($request);

        $kind = $this->str($data, 'kind', 20) ?? 'annuncio';
        if (!in_array($kind, self::KINDS, true)) {
            return $this->error($response, 'validation', 'Tipo generazione non valido.', 422);
        }

        $propertyId = (int) ($data['property_id'] ?? 0);
        $prop = $this->pdo->prepare('SELECT * FROM properties WHERE id = :id AND agency_id = 1');
        $prop->execute(['id' => $propertyId]);
        $property = $prop->fetch();
        if (!$property) {
            return $this->error($response, 'validation', 'Immobile non valido.', 422);
        }

        if (!$this->anthropic->isConfigured()) {
            return $this->error(
                $response,
                'ai_unavailable',
                'Generazione AI non disponibile: configurare ANTHROPIC_API_KEY nelle impostazioni del server.',
                503
            );
        }

        $extraNotes = $this->str($data, 'extra_notes', 1000);
        $userPrompt = $this->buildPrompt($kind, $property, $extraNotes);

        try {
            $result = $this->anthropic->chat($this->systemPrompt(), [
                ['role' => 'user', 'content' => $userPrompt],
            ], 1500);
        } catch (Throwable $e) {
            $this->logger->error('Generazione AI fallita', ['error' => $e->getMessage()]);
            return $this->error($response, 'ai_error', 'Generazione non riuscita. Riprova tra qualche istante.', 502);
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO ai_generations (agency_id, kind, property_id, prompt, output, model)
             VALUES (1, :kind, :pid, :prompt, :output, :model) RETURNING id'
        );
        $ins->execute([
            'kind' => $kind,
            'pid' => $propertyId,
            'prompt' => $userPrompt,
            'output' => $result['text'],
            'model' => $this->anthropic->model(),
        ]);

        return $this->json($response, [
            'data' => [
                'generation_id' => (int) $ins->fetchColumn(),
                'kind' => $kind,
                'output' => $result['text'],
                'model' => $this->anthropic->model(),
            ],
        ], 201);
    }

    /** PUT /api/admin/ai/generations/{id} — segna accettata/modificata. */
    public function accept(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $data = $this->body($request);

        $sets = ['accepted = :accepted', 'updated_at = now()'];
        $params = ['id' => $id, 'accepted' => filter_var($data['accepted'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'];

        if (isset($data['output']) && is_string($data['output'])) {
            $sets[] = 'output = :output';
            $params['output'] = mb_substr($data['output'], 0, 20000);
        }

        $stmt = $this->pdo->prepare('UPDATE ai_generations SET ' . implode(', ', $sets) . ' WHERE id = :id AND agency_id = 1');
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'not_found', 'Generazione non trovata.', 404);
        }

        return $this->json($response, ['ok' => true]);
    }

    private function systemPrompt(): string
    {
        return 'Sei il copywriter di RT — Roberto Tacchetto Real Estate Advisor (network Tecnocasa, Treviso e provincia). '
            . 'Scrivi SOLO in italiano. Tono: professionale, concreto, caldo ma mai urlato; valorizza i punti di forza reali senza esagerazioni. '
            . 'Brand: "Trasparenza. Controllo. Risultati." Non inventare caratteristiche non fornite. '
            . 'Rispondi SOLO con il testo richiesto, senza premesse né commenti.';
    }

    /** @param array<string,mixed> $property */
    private function buildPrompt(string $kind, array $property, ?string $extraNotes): string
    {
        $details = sprintf(
            "Dati immobile:\n- Titolo: %s\n- Tipologia: %s\n- Zona: %s%s\n- Superficie: %s mq\n- Locali: %s\n- Prezzo: %s\n- Descrizione interna: %s",
            $property['title'],
            $property['type'],
            $property['city'] ?? 'n.d.',
            $property['address'] ? ' (' . $property['address'] . ')' : '',
            $property['surface_sqm'] ?? 'n.d.',
            $property['rooms'] ?? 'n.d.',
            $property['price'] ? '€ ' . number_format((float) $property['price'], 0, ',', '.') : 'su richiesta',
            $property['description'] ?: 'non fornita'
        );

        $task = match ($kind) {
            'annuncio' => "Scrivi un annuncio immobiliare completo per i portali (Immobiliare.it/Idealista): titolo accattivante (max 80 caratteri) sulla prima riga, poi corpo di 150-250 parole ben strutturato con paragrafi, chiusura con invito a contattare Roberto Tacchetto al +39 345 7771822.",
            'post_social' => "Scrivi un post social (Facebook/Instagram) di 60-100 parole per promuovere questo immobile: hook nella prima riga, 2-3 emoji sobrie, 3-5 hashtag pertinenti in coda (es. #Treviso #immobiliare), CTA finale con telefono.",
            'descrizione' => 'Scrivi una descrizione breve ed elegante (40-60 parole) di questo immobile, adatta a schede sintetiche e vetrina.',
            'email' => 'Scrivi il corpo di una email di presentazione di questo immobile da inviare a potenziali acquirenti in lista: oggetto sulla prima riga (prefisso "Oggetto: "), poi corpo di 100-150 parole, tono personale, firma "Roberto Tacchetto — Real Estate Advisor".',
            'scheda' => "Scrivi il testo per una scheda di presentazione professionale di questo immobile destinata a potenziali acquirenti: un paragrafo introduttivo evocativo (60-80 parole), poi una sezione \"Punti di forza\" con 4-6 bullet concreti (uno per riga, prefisso \"- \"), infine un paragrafo di chiusura (30-40 parole) con invito alla visita. Non ripetere i dati numerici già in tabella (mq, prezzo, locali): valorizza contesto, luce, distribuzione degli spazi e zona.",
        };

        $notes = $extraNotes ? "\n\nIndicazioni aggiuntive dell'agente: {$extraNotes}" : '';

        return $details . "\n\n" . $task . $notes;
    }
}
