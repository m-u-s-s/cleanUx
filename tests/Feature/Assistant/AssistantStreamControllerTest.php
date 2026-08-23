<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantApiLog;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\Llm\AnthropicStreamingProvider;
use App\Services\Assistant\Streaming\StreamEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Couverture du AssistantStreamController : guards d'auth/validation + le vrai chemin de streaming (closure StreamedResponse) en faisant émettre une séquence de StreamEvent par un AnthropicStreamingProvider truqué. */
class AssistantStreamControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeConversationWithUserMessage(User $user, string $text = 'Bonjour'): array
    {
        $conv = AssistantConversation::create([
            'user_id' => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);

        $msg = AssistantMessage::create([
            'assistant_conversation_id' => $conv->id,
            'sender_type' => AssistantMessage::SENDER_USER,
            'content' => $text,
        ]);

        return [$conv, $msg];
    }

    private function signedStreamUrl(int $conversationId, int $userMessageId): string
    {
        return URL::temporarySignedRoute('assistant.stream', now()->addMinutes(5), [
            'conversation_id' => $conversationId,
            'user_message_id' => $userMessageId,
        ]);
    }

    /**
     * Binds a fake streamer that replays the provided StreamEvents through the controller callback.
     *
     * @param  array<int, StreamEvent>  $events
     */
    private function fakeStreamerEmitting(array $events): void
    {
        $fake = new class($events) extends AnthropicStreamingProvider
        {
            /** @var array<int, StreamEvent> */
            public array $events;

            public function __construct(array $events)
            {
                $this->events = $events;
            }

            public function chatStream(
                string $systemPrompt,
                array $messages,
                array $tools,
                callable $onEvent,
                array $options = []
            ): void {
                foreach ($this->events as $event) {
                    $onEvent($event);
                }
            }
        };

        $this->app->instance(AnthropicStreamingProvider::class, $fake);
    }

    private function fakeStreamerThrowing(string $message): void
    {
        $fake = new class($message) extends AnthropicStreamingProvider
        {
            public function __construct(public string $boom) {}

            public function chatStream(
                string $systemPrompt,
                array $messages,
                array $tools,
                callable $onEvent,
                array $options = []
            ): void {
                throw new \RuntimeException($this->boom);
            }
        };

        $this->app->instance(AnthropicStreamingProvider::class, $fake);
    }

    /** Drives the StreamedResponse closure while keeping PHPUnit's output buffering nesting consistent (the controller force-closes every ob level). */
    private function drainStream(TestResponse $response): void
    {
        $baseLevel = ob_get_level();
        ob_start();

        try {
            $response->baseResponse->sendContent();
        } finally {
            while (ob_get_level() > $baseLevel) {
                @ob_end_clean();
            }
            while (ob_get_level() < $baseLevel) {
                ob_start();
            }
        }
    }

    public function test_missing_conversation_or_message_id_returns_400(): void
    {
        $user = User::factory()->create();

        // conversation_id=0 is falsy → 400 before any DB lookup.
        $url = $this->signedStreamUrl(0, 5);

        $this->actingAs($user)->get($url)->assertStatus(400);
    }

    public function test_unknown_user_message_returns_404(): void
    {
        $user = User::factory()->create();
        [$conv] = $this->makeConversationWithUserMessage($user);

        // Message id that does not exist for this conversation.
        $url = $this->signedStreamUrl($conv->id, 999999);

        $this->actingAs($user)->get($url)->assertStatus(404);
    }

    public function test_assistant_message_of_wrong_sender_type_is_not_accepted(): void
    {
        $user = User::factory()->create();
        [$conv] = $this->makeConversationWithUserMessage($user);

        // An assistant message exists but the controller only accepts SENDER_USER.
        $assistant = AssistantMessage::create([
            'assistant_conversation_id' => $conv->id,
            'sender_type' => AssistantMessage::SENDER_ASSISTANT,
            'content' => 'previous answer',
        ]);

        $url = $this->signedStreamUrl($conv->id, $assistant->id);

        $this->actingAs($user)->get($url)->assertStatus(404);
    }

    public function test_streaming_happy_path_persists_assistant_message_and_logs_success(): void
    {
        $user = User::factory()->create();
        [$conv, $msg] = $this->makeConversationWithUserMessage($user, 'Quel temps fait-il ?');

        $this->fakeStreamerEmitting([
            StreamEvent::start('claude-sonnet-4-test', 42),
            StreamEvent::textDelta(0, 'Bonjour '),
            StreamEvent::textDelta(0, 'monde'),
            StreamEvent::messageDelta('end_turn', 17),
            StreamEvent::stop(),
        ]);

        $response = $this->actingAs($user)->get($this->signedStreamUrl($conv->id, $msg->id));
        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));

        $this->drainStream($response);

        // The accumulated text deltas were persisted as the assistant message.
        $assistant = AssistantMessage::query()
            ->where('assistant_conversation_id', $conv->id)
            ->where('sender_type', AssistantMessage::SENDER_ASSISTANT)
            ->first();

        $this->assertNotNull($assistant);
        $this->assertSame('Bonjour monde', $assistant->content);
        $this->assertTrue($assistant->metadata['streamed'] ?? false);
        $this->assertArrayNotHasKey('tool_uses', $assistant->metadata);

        // A success log row was recorded for the call.
        $log = AssistantApiLog::query()
            ->where('assistant_conversation_id', $conv->id)
            ->where('status', AssistantApiLog::STATUS_SUCCESS)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('anthropic', $log->provider);
        $this->assertSame('claude-sonnet-4-test', $log->model);
    }

    public function test_streaming_with_tool_use_persists_tool_metadata(): void
    {
        $user = User::factory()->create();
        [$conv, $msg] = $this->makeConversationWithUserMessage($user, 'Liste mes réservations');

        $this->fakeStreamerEmitting([
            StreamEvent::start('claude-sonnet-4-test', 10),
            StreamEvent::toolUseStart(0, 'toolu_123', 'list_my_bookings'),
            StreamEvent::toolInputDelta(0, '{"limit"'),
            StreamEvent::toolInputDelta(0, ':5}'),
            StreamEvent::messageDelta('tool_use', 8),
            StreamEvent::stop(),
        ]);

        $response = $this->actingAs($user)->get($this->signedStreamUrl($conv->id, $msg->id));
        $response->assertOk();

        $this->drainStream($response);

        $assistant = AssistantMessage::query()
            ->where('assistant_conversation_id', $conv->id)
            ->where('sender_type', AssistantMessage::SENDER_ASSISTANT)
            ->first();

        $this->assertNotNull($assistant);
        $this->assertSame('', $assistant->content);

        $toolUses = $assistant->metadata['tool_uses'] ?? null;
        $this->assertIsArray($toolUses);
        $this->assertCount(1, $toolUses);
        $this->assertSame('list_my_bookings', $toolUses[0]['name']);
        $this->assertSame('toolu_123', $toolUses[0]['id']);
        $this->assertSame(['limit' => 5], $toolUses[0]['input']);

        $log = AssistantApiLog::query()
            ->where('assistant_conversation_id', $conv->id)
            ->where('status', AssistantApiLog::STATUS_SUCCESS)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->tool_use_count);
    }

    public function test_streaming_exception_is_caught_and_logged_as_error(): void
    {
        $user = User::factory()->create();
        [$conv, $msg] = $this->makeConversationWithUserMessage($user);

        $this->fakeStreamerThrowing('upstream exploded');

        $response = $this->actingAs($user)->get($this->signedStreamUrl($conv->id, $msg->id));
        $response->assertOk();

        $this->drainStream($response);

        // No assistant message persisted because the stream blew up before persistence.
        $this->assertDatabaseMissing('assistant_messages', [
            'assistant_conversation_id' => $conv->id,
            'sender_type' => AssistantMessage::SENDER_ASSISTANT,
        ]);

        // An error log row was recorded.
        $log = AssistantApiLog::query()
            ->where('assistant_conversation_id', $conv->id)
            ->where('status', AssistantApiLog::STATUS_ERROR)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('anthropic', $log->provider);
    }
}
