<?php

namespace Tests\Feature\Assistant;

use App\Livewire\Chatbot\AssistantWidget;
use App\Models\AssistantAction;
use App\Models\AssistantApiLog;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AssistantWidgetActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_flips_is_open(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->assertSet('isOpen', false)
            ->call('toggle')
            ->assertSet('isOpen', true)
            ->call('toggle')
            ->assertSet('isOpen', false);
    }

    public function test_mount_loads_history_from_existing_open_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::create([
            'user_id' => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);
        AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'sender_type' => AssistantMessage::SENDER_USER,
            'content' => 'Bonjour assistant',
        ]);
        AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'sender_type' => AssistantMessage::SENDER_ASSISTANT,
            'content' => 'Bonjour, comment puis-je aider ?',
        ]);

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->assertSet('conversationId', $conversation->id);

        $messages = $component->get('messages');
        $this->assertCount(2, $messages);
        $this->assertSame('Bonjour assistant', $messages[0]['content']);
        $this->assertArrayHasKey('message_id', $messages[0]);
    }

    public function test_send_with_streaming_dispatches_event_and_persists_message(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('input', 'Réserve un ménage')
            ->call('send')
            ->assertSet('input', '')
            ->assertSet('isLoading', true)
            ->assertDispatched('assistant:stream-start');

        $this->assertDatabaseHas('assistant_messages', [
            'sender_type' => AssistantMessage::SENDER_USER,
            'content' => 'Réserve un ménage',
        ]);
        $this->assertDatabaseHas('assistant_conversations', [
            'user_id' => $user->id,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);

        $messages = $component->get('messages');
        $userMessages = array_filter($messages, fn ($m) => $m['sender'] === 'user');
        $this->assertNotEmpty($userMessages);
    }

    public function test_send_with_blank_input_is_no_op(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('input', '   ')
            ->call('send')
            ->assertNotDispatched('assistant:stream-start')
            ->assertSet('isLoading', false);

        $this->assertDatabaseCount('assistant_messages', 0);
    }

    public function test_send_is_blocked_by_per_minute_rate_limit(): void
    {
        $user = User::factory()->create();

        $key = 'assistant:minute:'.$user->id;
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($key, 60);
        }

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('input', 'Salut')
            ->call('send')
            ->assertNotDispatched('assistant:stream-start');

        $messages = $component->get('messages');
        $last = end($messages);
        $this->assertStringContainsString('Trop de messages', $last['content']);
        $this->assertDatabaseCount('assistant_messages', 0);
    }

    public function test_send_is_blocked_by_hourly_rate_limit(): void
    {
        $user = User::factory()->create();

        $perHour = (int) config('services.assistant.rate_per_hour', 30);
        for ($i = 0; $i < $perHour; $i++) {
            AssistantApiLog::create([
                'user_id' => $user->id,
                'provider' => 'mock',
                'model' => 'test',
                'status' => AssistantApiLog::STATUS_SUCCESS,
            ]);
        }

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('input', 'Encore une question')
            ->call('send')
            ->assertNotDispatched('assistant:stream-start');

        $messages = $component->get('messages');
        $last = end($messages);
        $this->assertStringContainsString('limite de messages', $last['content']);
    }

    public function test_stream_error_appends_message_and_clears_loading(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('isLoading', true)
            ->call('streamError', 'timeout serveur')
            ->assertSet('isLoading', false);

        $messages = $component->get('messages');
        $last = end($messages);
        $this->assertSame('assistant', $last['sender']);
        $this->assertStringContainsString('timeout serveur', $last['content']);
    }

    public function test_stream_completed_without_conversation_is_safe(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->assertSet('conversationId', null)
            ->set('isLoading', true)
            ->call('streamCompleted', null, false)
            ->assertSet('isLoading', false)
            ->assertSet('pendingActionId', null);
    }

    public function test_stream_completed_with_tools_loads_pending_action(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::create([
            'user_id' => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);
        AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'sender_type' => AssistantMessage::SENDER_ASSISTANT,
            'content' => 'Je prépare la réservation.',
        ]);
        $action = AssistantAction::create([
            'assistant_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'action_type' => 'create_booking',
            'status' => AssistantAction::STATUS_PENDING_CONFIRMATION,
            'payload' => ['tool_input' => []],
        ]);

        Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->assertSet('conversationId', $conversation->id)
            ->set('isLoading', true)
            ->call('streamCompleted', null, true)
            ->assertSet('isLoading', false)
            ->assertSet('pendingActionId', $action->id);
    }

    public function test_confirm_action_with_obsolete_tool_reports_error(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::create([
            'user_id' => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);
        $action = AssistantAction::create([
            'assistant_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'action_type' => 'tool_that_no_longer_exists',
            'status' => AssistantAction::STATUS_PENDING_CONFIRMATION,
            'payload' => ['tool_input' => []],
        ]);

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('pendingActionId', $action->id)
            ->call('confirmAction', $action->id)
            ->assertSet('pendingActionId', null);

        $messages = $component->get('messages');
        $last = end($messages);
        $this->assertStringContainsString('❌', $last['content']);

        $this->assertDatabaseHas('assistant_actions', [
            'id' => $action->id,
            'status' => AssistantAction::STATUS_FAILED,
        ]);
    }

    public function test_cancel_action_appends_message_and_clears_pending(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::create([
            'user_id' => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);
        $action = AssistantAction::create([
            'assistant_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'action_type' => 'create_booking',
            'status' => AssistantAction::STATUS_PENDING_CONFIRMATION,
            'payload' => ['tool_input' => []],
        ]);

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('pendingActionId', $action->id)
            ->call('cancelAction', $action->id)
            ->assertSet('pendingActionId', null);

        $messages = $component->get('messages');
        $last = end($messages);
        $this->assertStringContainsString('annulée', $last['content']);

        $this->assertDatabaseHas('assistant_actions', [
            'id' => $action->id,
            'status' => AssistantAction::STATUS_CANCELLED,
        ]);
    }

    public function test_clear_conversation_archives_and_resets_to_welcome(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::create([
            'user_id' => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);
        AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'sender_type' => AssistantMessage::SENDER_USER,
            'content' => 'un message',
        ]);

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->assertSet('conversationId', $conversation->id)
            ->call('clearConversation')
            ->assertSet('conversationId', null)
            ->assertSet('pendingActionId', null);

        $messages = $component->get('messages');
        $this->assertCount(1, $messages);
        $this->assertSame('assistant', $messages[0]['sender']);

        $this->assertDatabaseHas('assistant_conversations', [
            'id' => $conversation->id,
            'status' => AssistantConversation::STATUS_ARCHIVED,
        ]);
    }

    public function test_send_sync_mode_appends_assistant_reply(): void
    {
        $user = User::factory()->create();

        $this->mock(LlmClient::class, function ($mock) {
            $mock->shouldReceive('sendUserMessage')->once()->andReturn([
                'text' => 'Voici votre réponse synchrone.',
                'has_pending_action' => false,
                'pending_action_id' => null,
            ]);
        });

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('useStreaming', false)
            ->set('input', 'Bonjour')
            ->call('send')
            ->assertSet('isLoading', false)
            ->assertNotDispatched('assistant:stream-start');

        $messages = $component->get('messages');
        $last = end($messages);
        $this->assertStringContainsString('réponse synchrone', $last['content']);
    }

    public function test_send_sync_mode_handles_llm_exception_gracefully(): void
    {
        $user = User::factory()->create();

        $this->mock(LlmClient::class, function ($mock) {
            $mock->shouldReceive('sendUserMessage')->once()->andThrow(new RuntimeException('boom'));
        });

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->set('useStreaming', false)
            ->set('input', 'Provoque une erreur')
            ->call('send')
            ->assertSet('isLoading', false);

        $messages = $component->get('messages');
        $last = end($messages);
        $this->assertStringContainsString('Une erreur est survenue', $last['content']);
    }
}
