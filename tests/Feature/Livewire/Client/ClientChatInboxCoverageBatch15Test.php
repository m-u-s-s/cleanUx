<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\ClientChatInbox;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use Database\Factories\ChatMessageFactory;
use Database\Factories\ChatParticipantFactory;
use Database\Factories\ChatThreadFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class ClientChatInboxCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();

        Config::set('chat_v2.allowed_context_types', ['booking', 'dispute', 'admin', 'generic']);
        Config::set('chat_v2.min_message_length', 1);
        Config::set('chat_v2.max_message_length', 4096);
        Config::set('chat_v2.broadcast_enabled', false);
        Config::set('chat_v2.moderation.pii_redaction_enabled', true);
        Config::set('chat_v2.moderation.toxic_block_enabled', true);
        Config::set('chat_v2.moderation.pii_patterns', [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        ]);
        Config::set('chat_v2.moderation.toxic_words', ['idiot']);
    }

    protected function makeThreadForClient(array $threadAttrs = []): ChatThread
    {
        $thread = ChatThreadFactory::new()->create($threadAttrs);
        ChatParticipantFactory::new()->create([
            'thread_id' => $thread->id,
            'user_id' => $this->client->id,
            'role' => ChatParticipant::ROLE_CLIENT,
            'can_send' => true,
            'is_muted' => false,
            'left_at' => null,
        ]);

        return $thread;
    }

    public function test_render_lists_only_active_threads_for_user(): void
    {
        $mine = $this->makeThreadForClient(['title' => 'Ma conversation active']);
        $this->makeThreadForClient(['title' => 'Archivee', 'is_archived' => true, 'status' => ChatThread::STATUS_ARCHIVED]);

        // Thread where the client is not a participant.
        ChatThreadFactory::new()->create(['title' => 'Pas la mienne']);

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->assertOk()
            ->assertSee('Ma conversation active')
            ->assertDontSee('Archivee')
            ->assertDontSee('Pas la mienne');

        $this->assertSame(1, ChatThread::query()->forUser($this->client->id)->where('is_archived', false)->count());
    }

    public function test_select_thread_denied_when_not_participant(): void
    {
        $foreign = ChatThreadFactory::new()->create();

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->call('selectThread', $foreign->id)
            ->assertDispatched('toast')
            ->assertSet('activeThreadId', null);
    }

    public function test_select_thread_success_marks_as_read(): void
    {
        $thread = $this->makeThreadForClient();
        $other = User::factory()->employe()->create();
        ChatParticipantFactory::new()->create([
            'thread_id' => $thread->id,
            'user_id' => $other->id,
            'role' => ChatParticipant::ROLE_PROVIDER,
        ]);
        ChatMessageFactory::new()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $other->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->call('selectThread', $thread->id)
            ->assertSet('activeThreadId', $thread->id);

        $participant = ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $this->client->id)
            ->first();
        $this->assertNotNull($participant->last_read_at);
    }

    public function test_active_messages_excludes_deleted(): void
    {
        $thread = $this->makeThreadForClient();
        ChatMessageFactory::new()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $this->client->id,
            'body' => 'Message bien visible ici',
        ]);
        ChatMessageFactory::new()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $this->client->id,
            'body' => 'Message supprime cache',
            'is_deleted' => true,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->call('selectThread', $thread->id)
            ->assertSee('Message bien visible ici')
            ->assertDontSee('Message supprime cache');
    }

    public function test_send_noop_without_active_thread(): void
    {
        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->set('body', 'Bonjour')
            ->call('send')
            ->assertNotDispatched('toast');

        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_send_noop_with_blank_body(): void
    {
        $thread = $this->makeThreadForClient();

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->set('activeThreadId', $thread->id)
            ->set('body', '   ')
            ->call('send')
            ->assertNotDispatched('toast');

        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_send_clean_message_dispatches_success_and_clears_body(): void
    {
        $thread = $this->makeThreadForClient();

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->set('activeThreadId', $thread->id)
            ->set('body', 'Bonjour, on se voit demain a 9h')
            ->call('send')
            ->assertSet('body', '')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('chat_messages', [
            'thread_id' => $thread->id,
            'sender_user_id' => $this->client->id,
            'moderation_status' => ChatMessage::MODERATION_CLEAN,
        ]);
    }

    public function test_send_flagged_message_masks_pii(): void
    {
        $thread = $this->makeThreadForClient();

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->set('activeThreadId', $thread->id)
            ->set('body', 'Mon email est jean@test.com')
            ->call('send')
            ->assertSet('body', '')
            ->assertDispatched('toast');

        $msg = ChatMessage::query()->where('thread_id', $thread->id)->first();
        $this->assertSame(ChatMessage::MODERATION_FLAGGED, $msg->moderation_status);
    }

    public function test_send_toxic_message_dispatches_block_toast(): void
    {
        $thread = $this->makeThreadForClient();

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->set('activeThreadId', $thread->id)
            ->set('body', 'tu es un idiot')
            ->call('send')
            ->assertDispatched('toast');

        $msg = ChatMessage::query()->where('thread_id', $thread->id)->first();
        $this->assertSame(ChatMessage::MODERATION_BLOCKED, $msg->moderation_status);
    }

    public function test_send_on_archived_thread_dispatches_validation_error(): void
    {
        $thread = $this->makeThreadForClient([
            'is_archived' => true,
            'status' => ChatThread::STATUS_ARCHIVED,
        ]);

        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->set('activeThreadId', $thread->id)
            ->set('body', 'Un message apres archive')
            ->call('send')
            ->assertDispatched('toast');

        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_send_missing_thread_is_noop(): void
    {
        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->set('activeThreadId', 999999)
            ->set('body', 'Bonjour')
            ->call('send')
            ->assertNotDispatched('toast');

        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_refresh_listener_is_noop(): void
    {
        Livewire::actingAs($this->client)
            ->test(ClientChatInbox::class)
            ->call('refresh')
            ->assertOk();
    }
}
