<?php

namespace App\Services\Assistant\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Provider LLM utilisant l'API Anthropic Messages. */
class AnthropicProvider implements LlmProvider
{
    public function name(): string
    {
        return 'anthropic';
    }

    public function chat(
        string $systemPrompt,
        array $messages,
        array $tools = [],
        array $options = []
    ): LlmResponse {
        $apiKey = (string) config('services.anthropic.key');

        if (empty($apiKey)) {
            return LlmResponse::error("ANTHROPIC_API_KEY n'est pas configurée.");
        }

        $payload = [
            'model' => $options['model'] ?? config('services.anthropic.model', 'claude-sonnet-4-20250514'),
            'max_tokens' => $options['max_tokens'] ?? config('services.anthropic.max_tokens', 1024),
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $timeout = (int) config('services.anthropic.timeout', 30);
        $retries = (int) config('services.anthropic.retries', 1);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
                ->timeout($timeout)
                ->retry($retries, 500, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->post('https://api.anthropic.com/v1/messages', $payload);

            if (! $response->successful()) {
                $body = $response->json() ?? [];
                $msg = $body['error']['message'] ?? "HTTP {$response->status()}";
                Log::warning('AnthropicProvider non-2xx', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                return LlmResponse::error("API Anthropic: {$msg}");
            }

            return $this->parseResponse($response->json());

        } catch (Throwable $e) {
            report($e);

            return LlmResponse::error('Erreur réseau Anthropic: '.$e->getMessage());
        }
    }

    /** Convertit la réponse brute Anthropic en LlmResponse normalisée. */
    private function parseResponse(array $data): LlmResponse
    {
        $stopReason = (string) ($data['stop_reason'] ?? 'end_turn');
        $usage = $data['usage'] ?? [];

        $textParts = [];
        $toolUses = [];

        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $textParts[] = $block['text'] ?? '';
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolUses[] = [
                    'id' => $block['id'] ?? '',
                    'name' => $block['name'] ?? '',
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return new LlmResponse(
            text: implode("\n\n", array_filter($textParts)),
            stopReason: $stopReason,
            toolUses: $toolUses,
            usage: $usage,
        );
    }
}
