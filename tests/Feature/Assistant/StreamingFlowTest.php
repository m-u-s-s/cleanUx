<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\Streaming\StreamEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 5.2 — Tests du streaming.
 *
 * NB: on ne teste pas le vrai endpoint streaming (qui appelle l'API Anthropic
 * en direct via curl). On teste :
 *   - la génération d'URL signée + auth
 *   - la conversion StreamEvent ↔ format SSE
 *   - le AssistantWidget en mode streaming (dispatch du browser event)
 */
class StreamingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_url_is_generated_for_assistant_stream(): void
    {
        $user = User::factory()->create();
        $conv = AssistantConversation::create([
            'user_id'      => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status'       => AssistantConversation::STATUS_OPEN,
        ]);
        $msg = AssistantMessage::create([
            'assistant_conversation_id' => $conv->id,
            'sender_type'               => AssistantMessage::SENDER_USER,
            'content'                   => 'test',
        ]);

        $url = URL::temporarySignedRoute('assistant.stream', now()->addMinutes(5), [
            'conversation_id'  => $conv->id,
            'user_message_id'  => $msg->id,
        ]);

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('conversation_id=' . $conv->id, $url);
    }

    public function test_unauthorized_user_cannot_access_signed_url_of_another_user(): void
    {
        $owner   = User::factory()->create();
        $stranger = User::factory()->create();

        $conv = AssistantConversation::create([
            'user_id'      => $owner->id,
            'context_role' => $owner->assistantContextRole()->value,
            'status'       => AssistantConversation::STATUS_OPEN,
        ]);
        $msg = AssistantMessage::create([
            'assistant_conversation_id' => $conv->id,
            'sender_type'               => AssistantMessage::SENDER_USER,
            'content'                   => 'test',
        ]);

        $url = URL::temporarySignedRoute('assistant.stream', now()->addMinutes(5), [
            'conversation_id'  => $conv->id,
            'user_message_id'  => $msg->id,
        ]);

        // Stranger essaie d'accéder même avec une URL signée valide
        $response = $this->actingAs($stranger)->get($url);

        // Le controller fait `where('user_id', $user->id)` sur la conv → 404
        $response->assertStatus(404);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $conv = AssistantConversation::create([
            'user_id'      => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status'       => AssistantConversation::STATUS_OPEN,
        ]);
        $msg = AssistantMessage::create([
            'assistant_conversation_id' => $conv->id,
            'sender_type'               => AssistantMessage::SENDER_USER,
            'content'                   => 'test',
        ]);

        // URL sans signature
        $response = $this->actingAs($user)->get("/assistant/stream?conversation_id={$conv->id}&user_message_id={$msg->id}");

        $response->assertStatus(403);
    }

    public function test_expired_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $conv = AssistantConversation::create([
            'user_id'      => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status'       => AssistantConversation::STATUS_OPEN,
        ]);
        $msg = AssistantMessage::create([
            'assistant_conversation_id' => $conv->id,
            'sender_type'               => AssistantMessage::SENDER_USER,
            'content'                   => 'test',
        ]);

        // URL signée expirée (1 seconde dans le passé)
        $url = URL::temporarySignedRoute('assistant.stream', now()->subSecond(), [
            'conversation_id'  => $conv->id,
            'user_message_id'  => $msg->id,
        ]);

        $response = $this->actingAs($user)->get($url);
        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────
    // StreamEvent factories
    // ──────────────────────────────────────────────────────

    public function test_stream_event_factories_produce_correct_types(): void
    {
        $start = StreamEvent::start('claude-sonnet-4', 100);
        $this->assertSame(StreamEvent::TYPE_START, $start->type);
        $this->assertSame('claude-sonnet-4', $start->payload['model']);
        $this->assertSame(100, $start->payload['input_tokens']);

        $delta = StreamEvent::textDelta(0, 'Hello');
        $this->assertSame(StreamEvent::TYPE_TEXT_DELTA, $delta->type);
        $this->assertSame('Hello', $delta->payload['text']);

        $tool = StreamEvent::toolUseStart(0, 'toolu_abc', 'list_my_bookings');
        $this->assertSame(StreamEvent::TYPE_TOOL_USE_START, $tool->type);
        $this->assertSame('list_my_bookings', $tool->payload['tool_name']);

        $stop = StreamEvent::stop();
        $this->assertSame(StreamEvent::TYPE_STOP, $stop->type);

        $err = StreamEvent::error('boom');
        $this->assertSame(StreamEvent::TYPE_ERROR, $err->type);
        $this->assertSame('boom', $err->payload['message']);
    }

    public function test_stream_event_to_array_round_trip(): void
    {
        $event = StreamEvent::textDelta(2, 'World');
        $arr = $event->toArray();

        $this->assertSame('text_delta', $arr['type']);
        $this->assertSame(2, $arr['payload']['index']);
        $this->assertSame('World', $arr['payload']['text']);
    }
}
