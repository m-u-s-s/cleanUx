<?php

namespace App\Services\Assistant\Logging;

use App\Models\AssistantApiLog;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\Llm\LlmResponse;

/**
 * Phase 5.1 — Enregistrement des appels API LLM dans assistant_api_logs.
 *
 * Appelé par LlmClient à chaque tour (pas seulement à la fin de la boucle agentic),
 * pour avoir une vraie granularité par appel et pouvoir détecter une explosion
 * de tool_use loops par exemple.
 */
class LogRecorder
{
    public function __construct(
        protected CostCalculator $costCalculator,
    ) {}

    public function recordSuccess(
        ?User $user,
        ?AssistantConversation $conversation,
        string $provider,
        ?string $model,
        LlmResponse $response,
        int $latencyMs,
    ): AssistantApiLog {
        $usage  = $response->usage;
        $input  = (int) ($usage['input_tokens']  ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);

        return AssistantApiLog::create([
            'user_id'                   => $user?->id,
            'assistant_conversation_id' => $conversation?->id,
            'provider'                  => $provider,
            'model'                     => $model,
            'input_tokens'              => $input ?: null,
            'output_tokens'             => $output ?: null,
            'total_tokens'              => ($input + $output) ?: null,
            'cost_usd'                  => $this->costCalculator->compute($model, $input, $output),
            'latency_ms'                => $latencyMs,
            'status'                    => AssistantApiLog::STATUS_SUCCESS,
            'stop_reason'               => $response->stopReason,
            'tool_use_count'            => count($response->toolUses),
            'tools_used'                => array_map(fn ($t) => $t['name'] ?? '', $response->toolUses),
        ]);
    }

    public function recordError(
        ?User $user,
        ?AssistantConversation $conversation,
        string $provider,
        ?string $model,
        string $errorMessage,
        int $latencyMs,
        bool $isTimeout = false,
    ): AssistantApiLog {
        return AssistantApiLog::create([
            'user_id'                   => $user?->id,
            'assistant_conversation_id' => $conversation?->id,
            'provider'                  => $provider,
            'model'                     => $model,
            'latency_ms'                => $latencyMs,
            'status'                    => $isTimeout ? AssistantApiLog::STATUS_TIMEOUT : AssistantApiLog::STATUS_ERROR,
            'error_message'             => mb_substr($errorMessage, 0, 1000),
        ]);
    }
}
