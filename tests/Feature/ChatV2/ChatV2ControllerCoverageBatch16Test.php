<?php

namespace Tests\Feature\ChatV2;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\ChatV2\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatV2ControllerCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('chat_v2.allowed_context_types', ['booking', 'dispute', 'admin', 'generic']);
        Config::set('chat_v2.max_message_length', 4096);
        Config::set('chat_v2.broadcast_enabled', false);
        Config::set('chat_v2.attachments_disk', 'local');
        Config::set('chat_v2.moderation.pii_redaction_enabled', false);
        Config::set('chat_v2.moderation.toxic_block_enabled', false);
    }

    public function test_show_thread_returns_payload_for_participant(): void
    {
        $owner = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 101, [
            ['user_id' => $owner->id, 'role' => 'client'],
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v2/chat/threads/{$thread->id}");

        $response->assertOk();
        $this->assertSame($thread->id, $response->json('data.id'));
        $this->assertNotEmpty($response->json('data.participants'));
    }

    public function test_show_thread_forbidden_for_outsider(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 102, [
            ['user_id' => $owner->id, 'role' => 'client'],
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v2/chat/threads/{$thread->id}")
            ->assertStatus(403)
            ->assertJson(['ok' => false, 'error' => 'forbidden']);
    }

    public function test_list_my_threads_archived_filter_returns_only_archived(): void
    {
        $user = User::factory()->create();
        $svc = app(ChatService::class);
        $svc->startThread('booking', 201, [['user_id' => $user->id, 'role' => 'client']]);
        $archived = $svc->startThread('booking', 202, [['user_id' => $user->id, 'role' => 'client']]);
        $svc->archiveThread($archived);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v2/chat/threads?archived=1&limit=10');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(202, $response->json('data.0.context_id'));
    }

    public function test_create_thread_invalid_context_type_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/chat/threads', [
            'context_type' => 'totally-unknown-context',
            'context_id' => 301,
            'participants' => [['user_id' => $user->id, 'role' => 'client']],
        ]);

        $response->assertStatus(422)
            ->assertJson(['ok' => false]);
        $this->assertArrayHasKey('context_type', $response->json('errors'));
    }

    public function test_download_attachment_forbidden_for_outsider(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 401, [
            ['user_id' => $owner->id, 'role' => 'client'],
        ]);
        $message = ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $owner->id,
            'sender_role' => 'client',
            'body' => 'hi',
            'attachment_path' => 'chat/x.bin',
            'moderation_status' => ChatMessage::MODERATION_CLEAN,
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v2/chat/messages/{$message->id}/attachment")
            ->assertStatus(403);
    }

    public function test_download_attachment_404_when_no_attachment_path(): void
    {
        $owner = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 402, [
            ['user_id' => $owner->id, 'role' => 'client'],
        ]);
        $message = ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $owner->id,
            'sender_role' => 'client',
            'body' => 'no file here',
            'moderation_status' => ChatMessage::MODERATION_CLEAN,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v2/chat/messages/{$message->id}/attachment")
            ->assertStatus(404);
    }

    public function test_download_attachment_404_when_file_missing_on_disk(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 403, [
            ['user_id' => $owner->id, 'role' => 'client'],
        ]);
        $message = ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $owner->id,
            'sender_role' => 'client',
            'body' => 'ghost',
            'attachment_path' => 'chat/missing.bin',
            'moderation_status' => ChatMessage::MODERATION_CLEAN,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v2/chat/messages/{$message->id}/attachment")
            ->assertStatus(404);
    }

    public function test_download_attachment_streams_stored_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('chat/doc.txt', 'hello attachment');

        $owner = User::factory()->create();
        $thread = app(ChatService::class)->startThread('booking', 404, [
            ['user_id' => $owner->id, 'role' => 'client'],
        ]);
        $message = ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $owner->id,
            'sender_role' => 'client',
            'body' => 'see attached',
            'attachment_path' => 'chat/doc.txt',
            'attachment_mime' => 'text/plain',
            'moderation_status' => ChatMessage::MODERATION_CLEAN,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/v2/chat/messages/{$message->id}/attachment");

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('doc.txt', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('hello attachment', $response->getContent());
    }

    public function test_admin_list_threads_applies_status_and_flagged_filters(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->adminComplet()->create();
        $svc = app(ChatService::class);

        $flagged = $svc->startThread('booking', 501, [['user_id' => $user->id, 'role' => 'client']]);
        $flagged->update(['flagged_count' => 3, 'status' => ChatThread::STATUS_ACTIVE]);
        $svc->startThread('booking', 502, [['user_id' => $user->id, 'role' => 'client']]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/chat-v2/threads?status=active&flagged_only=1&limit=20');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($flagged->id, $ids);
    }

    public function test_admin_list_flagged_returns_flagged_and_blocked_messages(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->adminComplet()->create();
        $thread = app(ChatService::class)->startThread('booking', 601, [
            ['user_id' => $user->id, 'role' => 'client'],
        ]);

        ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $user->id,
            'sender_role' => 'client',
            'body' => 'flagged one',
            'moderation_status' => ChatMessage::MODERATION_FLAGGED,
        ]);
        ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $user->id,
            'sender_role' => 'client',
            'body' => 'blocked one',
            'moderation_status' => ChatMessage::MODERATION_BLOCKED,
        ]);
        ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $user->id,
            'sender_role' => 'client',
            'body' => 'clean one',
            'moderation_status' => ChatMessage::MODERATION_CLEAN,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/chat-v2/flagged?limit=50');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('moderation_status')->unique()->values()->all();
        $this->assertContains(ChatMessage::MODERATION_FLAGGED, $statuses);
        $this->assertContains(ChatMessage::MODERATION_BLOCKED, $statuses);
        $this->assertNotContains(ChatMessage::MODERATION_CLEAN, $statuses);
    }
}
