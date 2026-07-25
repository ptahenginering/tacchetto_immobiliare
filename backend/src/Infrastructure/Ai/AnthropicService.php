<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use RuntimeException;

/**
 * Client minimale API Anthropic (Messages API).
 * Env: ANTHROPIC_API_KEY, ANTHROPIC_MODEL (default claude-sonnet-4-6).
 */
final class AnthropicService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: '';
        $this->model = $model ?? $_ENV['ANTHROPIC_MODEL'] ?? getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-4-6';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @param string $system system prompt
     * @param array<int, array{role:'user'|'assistant', content:string}> $messages
     * @return array{text:string, tokens:int}
     * @throws RuntimeException se la chiamata fallisce o la chiave manca
     */
    public function chat(string $system, array $messages, int $maxTokens = 1000): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY non configurata.');
        }

        $payload = json_encode([
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Anthropic: errore di rete ({$curlErr})");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Anthropic: HTTP {$status} — " . substr((string) $body, 0, 500));
        }

        $decoded = json_decode((string) $body, true);
        $text = '';
        foreach (($decoded['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        $tokens = (int) (($decoded['usage']['input_tokens'] ?? 0) + ($decoded['usage']['output_tokens'] ?? 0));

        return ['text' => $text, 'tokens' => $tokens];
    }
}
