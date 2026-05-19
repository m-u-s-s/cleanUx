<?php

namespace Tests\Feature\ChatV2;

use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\ChatV2\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChatServiceTest extends TestCase
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

    public function test_start_thread_persists_and_attaches_participants(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $thread = app(ChatService::class)->startThread('booking', 42, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);

        $this->assertSame('booking', $thread->context_type);
        $this->assertSame(42, $thread->context_id);
        $this->assertSame(2, ChatParticipant::query()->where('thread_id', $thread->id)->count());
    }

    public function test_start_thread_is_idempotent_per_context(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $svc = app(ChatService::class);
        $t1 = $svc->startThread('booking', 7, [
            ['user_id' => $a->id, 'role' => 'client'],
        ]);
        $t2 = $svc->startThread('booking', 7, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);

        $this->assertSame($t1->id, $t2->id);
        $this->assertSame(2, ChatParticipant::query()->where('thread_id', $t1->id)->count());
    }

    public function test_start_thread_rejects_invalid_context_type(): void
    {
        $a = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(ChatService::class)->startThread('forbidden', 1, [
            ['user_id' => $a->id, 'role' => 'client'],
        ]);
    }

    public function test_send_message_persists_clean_message_and_updates_thread(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 1, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);

        $msg = $svc->sendMessage($thread, $a, 'Bonjour, à demain 9h');

        $this->assertSame(ChatMessage::MODERATION_CLEAN, $msg->moderation_status);
        $thread->refresh();
        $this->assertSame(1, $thread->message_count);
        $this->assertNotNull($thread->last_message_at);
    }

    public function test_send_message_with_pii_flagged_and_redacted(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 2, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);

        $msg = $svc->sendMessage($thread, $a, 'Mon email est jean@test.com');

        $this->assertSame(ChatMessage::MODERATION_FLAGGED, $msg->moderation_status);
        $this->assertStringContainsString('[REDACTED:email]', $msg->body);
        $this->assertTrue((bool) $msg->is_redacted);
        $this->assertNotNull($msg->body_original_hash);
        $thread->refresh();
        $this->assertSame(1, $thread->flagged_count);
    }

    public function test_send_message_toxic_blocks_and_increments_flagged_count(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 3, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);

        $msg = $svc->sendMessage($thread, $a, 'tu es un idiot');

        $this->assertSame(ChatMessage::MODERATION_BLOCKED, $msg->moderation_status);
        $this->assertSame('[message bloqué par modération]', $msg->displayBody());
        $thread->refresh();
        $this->assertSame(1, $thread->flagged_count);
    }

    public function test_send_message_rejects_non_participant(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $other = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 4, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);

        $this->expectException(ValidationException::class);
        $svc->sendMessage($thread, $other, 'Je suis intrus');
    }

    public function test_send_message_rejects_in_archived_thread(): void
    {
        $a = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 5, [
            ['user_id' => $a->id, 'role' => 'client'],
        ]);
        $svc->archiveThread($thread);

        $this->expectException(ValidationException::class);
        $svc->sendMessage($thread, $a, 'Test après archive');
    }

    public function test_mark_as_read_creates_reads_and_updates_participant(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 6, [
            ['user_id' => $a->id, 'role' => 'client'],
            ['user_id' => $b->id, 'role' => 'provider'],
        ]);

        $msg1 = $svc->sendMessage($thread, $a, 'message 1');
        $msg2 = $svc->sendMessage($thread, $a, 'message 2');

        $count = $svc->markAsRead($thread, $b);
        $this->assertSame(2, $count);

        $this->assertSame(2, ChatMessageRead::query()->where('user_id', $b->id)->count());
        $bParticipant = ChatParticipant::query()
            ->where('thread_id', $thread->id)->where('user_id', $b->id)->first();
        $this->assertSame($msg2->id, $bParticipant->last_read_message_id);
    }

    public function test_moderate_message_actions(): void
    {
        $a = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $svc = app(ChatService::class);
        $thread = $svc->startThread('booking', 8, [['user_id' => $a->id, 'role' => 'client']]);
        $msg = $svc->sendMessage($thread, $a, 'message normal');

        $svc->moderateMessage($msg, 'block', $admin->id, 'admin block test');
        $this->assertSame(ChatMessage::MODERATION_BLOCKED, $msg->fresh()->moderation_status);

        $svc->moderateMessage($msg, 'approve', $admin->id);
        $this->assertSame(ChatMessage::MODERATION_CLEAN, $msg->fresh()->moderation_status);

        $svc->moderateMessage($msg, 'delete', $admin->id);
        $this->assertTrue((bool) $msg->fresh()->is_deleted);
        $this->assertSame('[message supprimé]', $msg->fresh()->displayBody());
    }
}
