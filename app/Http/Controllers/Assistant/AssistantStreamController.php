<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\Llm\AnthropicStreamingProvider;
use App\Services\Assistant\Logging\LogRecorder;
use App\Services\Assistant\Streaming\StreamEvent;
use App\Services\Assistant\Tools\AssistantToolDispatcher;
use App\Services\Assistant\Tools\AssistantToolRegistry;
use App\Services\AssistantContextBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 5.1 — Endpoint SSE de streaming pour l'assistant.
 *
 * Le front (AssistantWidget) lance un `EventSource('/assistant/stream?...')` et
 * reçoit progressivement les chunks de texte au fur et à mesure que le LLM répond.
 *
 * Limites :
 *   - L'endpoint est à long-running (peut durer 30-60s).
 *   - PHP-FPM peut couper avant la fin si max_execution_time trop bas.
 *   - Pour Reverb/WebSocket, faire un wrapper async dédié (Phase 6).
 *
 * Usage côté JS :
 *   const es = new EventSource('/assistant/stream?conversation_id=42&message=...');
 *   es.addEventListener('text_delta', (e) => append(JSON.parse(e.data).text));
 *   es.addEventListener('stop', () => es.close());
 */
class AssistantStreamController extends Controller
{
    public function stream(
        Request $request,
        AnthropicStreamingProvider $streamer,
        AssistantContextBuilder $contextBuilder,
        AssistantToolRegistry $registry,
        AssistantToolDispatcher $dispatcher,
        LogRecorder $logRecorder,
    ): StreamedResponse {
        $user           = $request->user();
        abort_if(! $user, 401);

        $conversationId = (int) $request->query('conversation_id');
        $userMessage    = trim((string) $request->query('message'));

        abort_if(! $userMessage || ! $conversationId, 400, 'Missing message or conversation_id');

        $conversation = AssistantConversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        abort_if(! $conversation, 404);

        // Persister le message utilisateur
        AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'sender_type'               => AssistantMessage::SENDER_USER,
            'content'                   => $userMessage,
        ]);

        return new StreamedResponse(function () use (
            $user,
            $conversation,
            $userMessage,
            $streamer,
            $contextBuilder,
            $registry,
            $dispatcher,
            $logRecorder,
        ) {
            $context = $contextBuilder->build($user);
            $tools   = $registry->definitionsForUser($user);
            $messages = $this->buildMessageHistory($conversation);

            $startTime = microtime(true);
            $accText   = '';
            $toolUses  = [];

            try {
                foreach ($streamer->chatStream($context['system'], $messages, $tools) as $event) {
                    // Émet vers le client
                    $this->emit($event);

                    // Accumule pour persistence finale
                    if ($event->type === StreamEvent::TYPE_TEXT_DELTA) {
                        $accText .= $event->payload['text'] ?? '';
                    } elseif ($event->type === StreamEvent::TYPE_TOOL_USE_START) {
                        $toolUses[$event->payload['index']] = [
                            'id'    => $event->payload['tool_use_id'],
                            'name'  => $event->payload['tool_name'],
                            'input' => '',
                        ];
                    } elseif ($event->type === StreamEvent::TYPE_TOOL_INPUT_DELTA) {
                        $idx = $event->payload['index'];
                        if (isset($toolUses[$idx])) {
                            $toolUses[$idx]['input'] .= $event->payload['json_chunk'] ?? '';
                        }
                    } elseif ($event->type === StreamEvent::TYPE_STOP) {
                        break;
                    } elseif ($event->type === StreamEvent::TYPE_ERROR) {
                        break;
                    }

                    if (connection_aborted()) {
                        break;
                    }
                }

                // Persiste le message assistant final
                $finalToolUses = array_values(array_map(function ($t) {
                    $t['input'] = json_decode($t['input'], true) ?: [];
                    return $t;
                }, $toolUses));

                AssistantMessage::create([
                    'assistant_conversation_id' => $conversation->id,
                    'sender_type'               => AssistantMessage::SENDER_ASSISTANT,
                    'content'                   => $accText,
                    'metadata'                  => $finalToolUses
                        ? ['tool_uses' => $finalToolUses]
                        : null,
                ]);

                // Logue (latence en ms)
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
                // Note: pour le streaming on ne récupère pas usage tokens dans la réponse
                // streamée (Anthropic les met dans message_delta — voir AnthropicStreamingProvider).
                // Pour un tracking précis, accumuler dans la boucle.

            } catch (\Throwable $e) {
                $this->emit(StreamEvent::error($e->getMessage()));
                $logRecorder->recordError(
                    $user,
                    $conversation,
                    'anthropic',
                    config('services.anthropic.model'),
                    $e->getMessage(),
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no', // désactive le buffering nginx
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Émet un StreamEvent au format SSE :
     *   event: text_delta
     *   data: {"index": 0, "text": "Hello"}
     *
     *   (\n\n termine le frame)
     */
    private function emit(StreamEvent $event): void
    {
        echo "event: {$event->type}\n";
        echo "data: " . json_encode($event->payload, JSON_UNESCAPED_UNICODE) . "\n\n";

        if (function_exists('ob_flush')) @ob_flush();
        flush();
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
