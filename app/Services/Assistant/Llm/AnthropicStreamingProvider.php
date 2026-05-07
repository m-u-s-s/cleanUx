<?php

namespace App\Services\Assistant\Llm;

use App\Services\Assistant\Streaming\StreamEvent;

/**
 * Phase 5.2 — Streaming temps réel avec callback pattern.
 *
 * Différence clé avec Phase 5.1 :
 *   AVANT : accumulait tous les events curl dans un array, puis yield après
 *           curl_exec (= pseudo-streaming, l'utilisateur attend toute la réponse).
 *   APRÈS : invoque le callback à CHAQUE event SSE reçu, depuis l'intérieur
 *           du WRITEFUNCTION callback de curl. Le contrôleur peut donc écrire
 *           sur le flux de réponse HTTP au fur et à mesure → vrai streaming.
 *
 * Usage :
 *   $provider->chatStream($system, $messages, $tools, function (StreamEvent $event) {
 *       echo "event: {$event->type}\n";
 *       echo "data: " . json_encode($event->payload) . "\n\n";
 *       flush();
 *   });
 */
class AnthropicStreamingProvider
{
    /**
     * Stream une conversation, invoquant $onEvent pour chaque StreamEvent
     * dès qu'il arrive du serveur Anthropic.
     *
     * @param callable(StreamEvent): void $onEvent
     */
    public function chatStream(
        string $systemPrompt,
        array $messages,
        array $tools,
        callable $onEvent,
        array $options = []
    ): void {
        $apiKey = (string) config('services.anthropic.key');

        if (empty($apiKey)) {
            $onEvent(StreamEvent::error("ANTHROPIC_API_KEY n'est pas configurée."));
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

        $ch     = curl_init('https://api.anthropic.com/v1/messages');
        $buffer = '';

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $options['timeout'] ?? 120,
            CURLOPT_RETURNTRANSFER => false,

            // Le streaming réel : à chaque chunk reçu de Anthropic, on parse
            // les frames complètes et on appelle $onEvent immédiatement.
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buffer, $onEvent) {
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $rawFrame = substr($buffer, 0, $pos);
                    $buffer   = substr($buffer, $pos + 2);

                    $frame = $this->parseSseFrame($rawFrame);
                    $event = $this->mapToStreamEvent($frame);

                    if ($event !== null) {
                        try {
                            $onEvent($event);
                        } catch (\Throwable $e) {
                            // Si le callback explose (client déconnecté par ex.),
                            // on coupe le stream gracieusement.
                            return -1; // abort curl
                        }
                    }
                }

                // Si le client a fermé la connexion, on arrête.
                if (connection_aborted()) {
                    return -1;
                }

                return strlen($chunk);
            },
        ]);

        $success = curl_exec($ch);
        $error   = curl_error($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! $success && ! connection_aborted()) {
            $onEvent(StreamEvent::error("Erreur réseau Anthropic: {$error} (HTTP {$code})"));
        }
    }

    /**
     * Parse une frame SSE :
     *   "event: content_block_delta\ndata: {...json...}"
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

        $data = $dataLines
            ? (json_decode(implode("\n", $dataLines), true) ?? [])
            : [];

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

            'ping' => null,

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
