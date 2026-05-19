<?php

namespace Tests\Feature\ChatV2;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\ChatV2\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('chat_v2.allowed_context_types', ['booking', 'dispute', 'admin', 'generic']);
        Config::set('chat_v2.max_message_length', 4096);
        Config::set('chat_v2.broadcast_enabled', false);
        Config::set('chat_v2.moderation.pii_redaction_enabled', true);
        Config::set('chat_v2.moderation.toxic_block_enabled', true);
        Config::set('chat_v2.moderation.pii_patterns', [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        ]);
        Config::set('chat_v2.moderation.toxic_words', ['idiot']);
    }

    public function test_create_thread_requires_auth(): void
    {
        $this->postJson('/api/v2/chat/threads', [
            'context_type' => 'booking',
            'context_id' => 1,
            'participants' => [['user_id' => 1, 'role' => 'client']],
        ])->assertStatus(401);
    }

    public function test_create_thread_returns_201_and_includes_current_user_as_participant(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/chat/threads', [
            'context_type' => 'booking',
            'context_id' => 11,
            'participants' => [['user_id' => $other->id, 'role' => 'provider']],
        ]);

        $response->assertCreated();
        $participants = $response->json('thread.participants');
        $userIds = collect($participants)->pluck('user_id')->all();
        $this->assertContains($user->id, $userIds);
        $this->assertContains($other->id, $userIds);
    }

    public function test_send_message_persists_and_returns_moderation_status(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 21, [
            ['user_id' => $user->id, 'role' => 'client'],
            ['user_id' => $other->id, 'role' => 'provider'],
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v2/chat/threads/{$thread->id}/messages", [
            'body' => 'Confirmé pour demain à 14h',
        ]);

        $response->assertCreated();
        $this->assertSame('clean', $response->json('moderation_status'));
    }

    public function test_send_message_with_pii_returns_flagged_status(): void
    {
        $user = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 22, [
            ['user_id' => $user->id, 'role' => 'client'],
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v2/chat/threads/{$thread->id}/messages", [
            'body' => 'Mon email est test@example.com',
        ]);

        $response->assertCreated();
        $this->assertSame('flagged', $response->json('moderation_status'));
    }

    public function test_send_message_to_thread_user_not_in_is_403(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 23, [
            ['user_id' => $owner->id, 'role' => 'client'],
        ]);

        Sanctum::actingAs($intruder);
        $this->postJson("/api/v2/chat/threads/{$thread->id}/messages", [
            'body' => 'Hello',
        ])->assertStatus(403);
    }

    public function test_send_message_rejects_empty_body_without_attachment(): void
    {
        $user = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 24, [
            ['user_id' => $user->id, 'role' => 'client'],
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/chat/threads/{$thread->id}/messages", [])
            ->assertStatus(422);
    }

    public function test_list_messages_returns_only_for_participant(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 25, [
            ['user_id' => $user->id, 'role' => 'client'],
        ]);
        $svc->sendMessage($thread, $user, 'hello world');

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/v2/chat/threads/{$thread->id}/messages");
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));

        Sanctum::actingAs($other);
        $this->getJson("/api/v2/chat/threads/{$thread->id}/messages")->assertStatus(403);
    }

    public function test_list_my_threads_returns_own_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $svc = app(ChatService::class);
        $svc->startThread('booking', 31, [['user_id' => $user->id, 'role' => 'client']]);
        $svc->startThread('booking', 32, [['user_id' => $other->id, 'role' => 'client']]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v2/chat/threads');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(31, $response->json('data.0.context_id'));
    }

    public function test_mark_as_read_returns_count(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 41, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);
        $svc->sendMessage($thread, $a, 'msg 1');
        $svc->sendMessage($thread, $a, 'msg 2');

        Sanctum::actingAs($b);
        $response = $this->postJson("/api/v2/chat/threads/{$thread->id}/read");
        $response->assertOk();
        $this->assertSame(2, $response->json('marked'));
    }

    public function test_archive_thread_marks_archived(): void
    {
        $user = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 51, [
            ['user_id' => $user->id, 'role' => 'client'],
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/chat/threads/{$thread->id}/archive")->assertOk();
        $this->assertTrue((bool) $thread->fresh()->is_archived);
    }

    public function test_admin_moderate_blocks_message(): void
    {
        $a = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 61, [['user_id' => $a->id, 'role' => 'client']]);
        $msg = $svc->sendMessage($thread, $a, 'message à modérer');

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/chat-v2/messages/{$msg->id}/moderate", [
            'action' => 'block',
            'reason' => 'Contenu inapproprié',
        ]);
        $response->assertOk();
        $this->assertSame(ChatMessage::MODERATION_BLOCKED, $msg->fresh()->moderation_status);
    }

    public function test_admin_moderate_validates_action(): void
    {
        $a = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 62, [['user_id' => $a->id, 'role' => 'client']]);
        $msg = $svc->sendMessage($thread, $a, 'message');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/chat-v2/messages/{$msg->id}/moderate", [
            'action' => 'unknown',
        ])->assertStatus(422);
    }
}
