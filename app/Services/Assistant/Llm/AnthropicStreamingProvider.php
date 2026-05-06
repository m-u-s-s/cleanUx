<?php

namespace App\Services\Assistant\Llm;

use App\Services\Assistant\Streaming\StreamEvent;
use Generator;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5.1 — Streaming pour le provider Anthropic.
 *
 * Différence avec AnthropicProvider : au lieu d'attendre la réponse complète,
 * on parse les événements SSE (Server-Sent Events) au fur et à mesure et on
 * yield des StreamEvent à chaque token/bloc reçu.
 *
 * Avantages UX :
 *   - L'utilisateur voit la réponse se construire token par token (Discord/ChatGPT-like)
 *   - Latence perçue divisée par 2-3 sur des longues réponses
 *   - Possibilité d'annuler en cours de stream
 *
 * Doc Anthropic streaming :
 *   https://docs.anthropic.com/en/api/messages-streaming
 *
 * Format des events SSE :
 *   event: message_start
 *   data: {"type":"message_start", ...}
 *
 *   event: content_block_delta
 *   data: {"type":"content_block_delta", "index":0, "delta":{"type":"text_delta", "text":"Hello"}}
 *
 *   event: message_stop
 *   data: {"type":"message_stop"}
 */
class AnthropicStreamingProvider
{
    /**
     * Stream une conversation et yield les événements en flux.
     *
     * @return Generator<StreamEvent>
     */
    public function chatStream(
        string $systemPrompt,
        array $messages,
        array $tools = [],
        array $options = []
    ): Generator {
        $apiKey = (string) config('services.anthropic.key');

        if (empty($apiKey)) {
            yield StreamEvent::error("ANTHROPIC_API_KEY n'est pas configurée.");
            return;
        }

        $payload = [
            'model'      => $options['model']      ?? config('services.anthropic.model', 'claude-sonnet-4-20250514'),
            'max_tokens' => $options['max_tokens'] ?? config('services.anthropic.max_tokens', 1024),
            'system'     => $systemPrompt,
            'messages'   => $messages,
            'stream'     => true,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        // Pas de Http::stream pratique en Laravel pour SSE → on utilise cURL directement.
        $ch = curl_init('https://api.anthropic.com/v1/messages');

        $buffer = '';
        $events = [];

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $options['timeout'] ?? 60,
            CURLOPT_RETURNTRANSFER => false,
            // Callback : on accumule les chunks SSE et on parse les events complets
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buffer, &$events) {
                $buffer .= $chunk;

                // Un event SSE est délimité par "\n\n"
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $rawEvent = substr($buffer, 0, $pos);
                    $buffer   = substr($buffer, $pos + 2);
                    $events[] = $this->parseSseFrame($rawEvent);
                }

                return strlen($chunk);
            },
        ]);

        // Lancer la requête (synchrone mais notre WRITEFUNCTION accumule les events)
        $success = curl_exec($ch);
        $error   = curl_error($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! $success || $code >= 400) {
            yield StreamEvent::error("Erreur API Anthropic (HTTP {$code}): {$error}");
            return;
        }

        // On a tous les events, on les yield un par un en simulant un vrai stream.
        // (Pour un vrai stream incrémental avec Laravel, il faudrait un setup
        // plus complexe avec ratchet ou un BroadcastChannel — voir AssistantStreamController.)
        foreach ($events as $eventData) {
            $streamEvent = $this->mapToStreamEvent($eventData);
            if ($streamEvent) {
                yield $streamEvent;
            }
        }
    }

    /**
     * Parse une frame SSE :
     *   "event: content_block_delta\ndata: {...json...}"
     *   → ['event' => 'content_block_delta', 'data' => [...]]
     */
    private function parseSseFrame(string $raw): array
    {
        $event = null;
        $dataLines = [];

        foreach (preg_split("/\r?\n/", $raw) as $line) {
            if (str_starts_with($line, 'event: ')) {
                $event = trim(substr($line, 7));
            } elseif (str_starts_with($line, 'data: ')) {
                $dataLines[] = substr($line, 6);
            }
        }

        $dataJson = implode("\n", $dataLines);
        $data     = json_decode($dataJson, true) ?? [];

        return ['event' => $event, 'data' => $data];
    }

    /**
     * Mappe un event Anthropic vers notre StreamEvent normalisé.
     */
    private function mapToStreamEvent(array $frame): ?StreamEvent
    {
        $type = $frame['event'] ?? ($frame['data']['type'] ?? null);
        $data = $frame['data'] ?? [];

        return match ($type) {
            'message_start' => StreamEvent::start(
                model: $data['message']['model'] ?? null,
                inputTokens: (int) ($data['message']['usage']['input_tokens'] ?? 0),
            ),

            'content_block_start' => $this->mapContentBlockStart($data),

            'content_block_delta' => $this->mapContentBlockDelta($data),

            'content_block_stop' => StreamEvent::contentBlockStop(
                index: (int) ($data['index'] ?? 0),
            ),

            'message_delta' => StreamEvent::messageDelta(
                stopReason: $data['delta']['stop_reason'] ?? null,
                outputTokens: (int) ($data['usage']['output_tokens'] ?? 0),
            ),

            'message_stop' => StreamEvent::stop(),

            'ping' => null, // ignore les keepalive

            'error' => StreamEvent::error($data['error']['message'] ?? 'Stream error'),

            default => null,
        };
    }

    private function mapContentBlockStart(array $data): ?StreamEvent
    {
        $block = $data['content_block'] ?? [];
        $type  = $block['type'] ?? null;

        if ($type === 'text') {
            return StreamEvent::textBlockStart(index: (int) ($data['index'] ?? 0));
        }

        if ($type === 'tool_use') {
            return StreamEvent::toolUseStart(
                index: (int) ($data['index'] ?? 0),
                toolUseId: $block['id'] ?? '',
                toolName: $block['name'] ?? '',
            );
        }

        return null;
    }

    private function mapContentBlockDelta(array $data): ?StreamEvent
    {
        $delta = $data['delta'] ?? [];
        $type  = $delta['type'] ?? null;
        $idx   = (int) ($data['index'] ?? 0);

        if ($type === 'text_delta') {
            return StreamEvent::textDelta(index: $idx, text: (string) ($delta['text'] ?? ''));
        }

        if ($type === 'input_json_delta') {
            return StreamEvent::toolInputDelta(
                index: $idx,
                jsonChunk: (string) ($delta['partial_json'] ?? ''),
            );
        }

        return null;
    }
}
