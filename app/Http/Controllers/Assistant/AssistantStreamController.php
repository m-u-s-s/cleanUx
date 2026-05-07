<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\Llm\AnthropicStreamingProvider;
use App\Services\Assistant\Logging\LogRecorder;
use App\Services\Assistant\Streaming\StreamEvent;
use App\Services\Assistant\Tools\AssistantToolRegistry;
use App\Services\AssistantContextBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 5.2 — Endpoint SSE de streaming, refactoré pour vrai temps réel.
 *
 * Différence clé avec 5.1 :
 *   AVANT : tous les events étaient accumulés puis émis en bloc à la fin.
 *   APRÈS : chaque event Anthropic est immédiatement émis vers le client
 *           via le callback du AnthropicStreamingProvider.
 *
 * Authentification :
 *   EventSource ne supporte pas les headers custom (CSRF). On utilise donc
 *   une URL signée (URL::temporarySignedRoute) générée par AssistantWidget
 *   pour authentifier l'appel.
 */
class AssistantStreamController extends Controller
{
    public function stream(
        Request $request,
        AnthropicStreamingProvider $streamer,
        AssistantContextBuilder $contextBuilder,
        AssistantToolRegistry $registry,
        LogRecorder $logRecorder,
    ): StreamedResponse {
        $user = $request->user();
        abort_if(! $user, 401);

        $conversationId = (int) $request->query('conversation_id');
        $userMessageId  = (int) $request->query('user_message_id');

        abort_if(! $conversationId || ! $userMessageId, 400, 'Missing conversation_id or user_message_id');

        $conversation = AssistantConversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        abort_if(! $conversation, 404, 'Conversation not found');

        // Le message utilisateur a été persisté côté Livewire AVANT de générer
        // l'URL signée — on le valide ici.
        $userMessage = AssistantMessage::query()
            ->where('id', $userMessageId)
            ->where('assistant_conversation_id', $conversation->id)
            ->where('sender_type', AssistantMessage::SENDER_USER)
            ->first();

        abort_if(! $userMessage, 404, 'User message not found');

        return new StreamedResponse(function () use (
            $user, $conversation, $streamer, $contextBuilder, $registry, $logRecorder
        ) {
            // Désactive le buffering PHP & nginx pour vraie diffusion live
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', 'off');
            while (ob_get_level()) {
                @ob_end_flush();
            }

            $context  = $contextBuilder->build($user);
            $tools    = $registry->definitionsForUser($user);
            $messages = $this->buildMessageHistory($conversation);

            $startTime    = microtime(true);
            $accText      = '';
            $toolUses     = [];
            $outputTokens = 0;
            $stopReason   = null;
            $modelUsed    = null;

            try {
                $streamer->chatStream(
                    $context['system'],
                    $messages,
                    $tools,
                    function (StreamEvent $event) use (
                        &$accText, &$toolUses, &$outputTokens, &$stopReason, &$modelUsed
                    ) {
                        // 1. Émission immédiate au client
                        $this->emit($event);

                        // 2. Accumulation pour persistence finale
                        switch ($event->type) {
                            case StreamEvent::TYPE_START:
                                $modelUsed = $event->payload['model'] ?? null;
                                break;
                            case StreamEvent::TYPE_TEXT_DELTA:
                                $accText .= $event->payload['text'] ?? '';
                                break;
                            case StreamEvent::TYPE_TOOL_USE_START:
                                $toolUses[$event->payload['index']] = [
                                    'id'    => $event->payload['tool_use_id'],
                                    'name'  => $event->payload['tool_name'],
                                    'input' => '',
                                ];
                                break;
                            case StreamEvent::TYPE_TOOL_INPUT_DELTA:
                                $idx = $event->payload['index'];
                                if (isset($toolUses[$idx])) {
                                    $toolUses[$idx]['input'] .= $event->payload['json_chunk'] ?? '';
                                }
                                break;
                            case StreamEvent::TYPE_MESSAGE_DELTA:
                                $stopReason   = $event->payload['stop_reason']   ?? $stopReason;
                                $outputTokens = $event->payload['output_tokens'] ?? $outputTokens;
                                break;
                        }
                    }
                );

                // Persiste le message assistant final
                $finalToolUses = array_values(array_map(function ($t) {
                    $t['input'] = json_decode($t['input'], true) ?: [];
                    return $t;
                }, $toolUses));

                $assistantMessage = AssistantMessage::create([
                    'assistant_conversation_id' => $conversation->id,
                    'sender_type'               => AssistantMessage::SENDER_ASSISTANT,
                    'content'                   => $accText,
                    'metadata'                  => $finalToolUses
                        ? ['tool_uses' => $finalToolUses, 'streamed' => true]
                        : ['streamed' => true],
                ]);

                // Émet un event de fin avec l'ID du message persisté
                $this->emitRaw('persisted', [
                    'message_id'  => $assistantMessage->id,
                    'has_tools'   => count($finalToolUses) > 0,
                ]);

                // Logue l'appel
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                // Pour le streaming on construit une LlmResponse pseudo pour réutiliser le LogRecorder
                $pseudoResponse = new \App\Services\Assistant\Llm\LlmResponse(
                    text: $accText,
                    stopReason: $stopReason ?? 'end_turn',
                    toolUses: $finalToolUses,
                    usage: ['input_tokens' => 0, 'output_tokens' => $outputTokens],
                );

                $logRecorder->recordSuccess(
                    $user,
                    $conversation,
                    'anthropic',
                    $modelUsed ?? config('services.anthropic.model'),
                    $pseudoResponse,
                    $latencyMs,
                );

            } catch (\Throwable $e) {
                report($e);
                $this->emit(StreamEvent::error("Erreur de streaming : " . $e->getMessage()));
                $logRecorder->recordError(
                    $user,
                    $conversation,
                    'anthropic',
                    $modelUsed ?? config('services.anthropic.model'),
                    $e->getMessage(),
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',     // désactive le buffering nginx
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Émet un StreamEvent au format SSE et flush immédiatement.
     */
    private function emit(StreamEvent $event): void
    {
        $this->emitRaw($event->type, $event->payload);
    }

    private function emitRaw(string $eventName, array $payload): void
    {
        echo "event: {$eventName}\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }

    private function buildMessageHistory(AssistantConversation $conversation): array
    {
        $rows = AssistantMessage::query()
            ->where('assistant_conversation_id', $conversation->id)
            ->whereIn('sender_type', [
                AssistantMessage::SENDER_USER,
                AssistantMessage::SENDER_ASSISTANT,
                AssistantMessage::SENDER_TOOL_RESULT,
            ])
            ->orderBy('id')
            ->get()
            ->reverse()
            ->take(20)
            ->reverse()
            ->values();

        return $rows
            ->map(fn (AssistantMessage $m) => $m->toApiPayload())
            ->filter(fn ($p) => ! empty($p))
            ->values()
            ->all();
    }
}
