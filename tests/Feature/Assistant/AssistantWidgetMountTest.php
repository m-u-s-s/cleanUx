<?php

namespace Tests\Feature\Assistant;

use App\Livewire\Chatbot\AssistantWidget;
use App\Models\User;
use App\Services\Assistant\QuickActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantWidgetMountTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_mounts_with_quick_actions(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->assertSet('quickActions', app(QuickActions::class)->forUser($user));
    }

    public function test_widget_mounts_with_welcome_message(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(AssistantWidget::class);

        $messages = $component->get('messages');
        $this->assertCount(1, $messages);
        $this->assertSame('assistant', $messages[0]['sender']);
        $this->assertNotEmpty($messages[0]['content']);
    }

    public function test_send_quick_with_valid_index_sets_input_and_triggers_send(): void
    {
        $user    = User::factory()->create();
        $actions = app(QuickActions::class)->forUser($user);

        // We only test that sendQuick populates $input from the right index.
        // The actual send() dispatches a signed URL and a streaming event which
        // requires a full HTTP context — we guard against that by checking state
        // after the component has handled the action (send() will fail gracefully
        // since there's no persisted conversation to target, but input gets cleared).
        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->call('sendQuick', 0);

        // After send(), $input is reset to ''
        $component->assertSet('input', '');

        // At least one user message should now be in $messages (from send())
        $messages = $component->get('messages');
        $userMessages = array_filter($messages, fn ($m) => $m['sender'] === 'user');
        $this->assertNotEmpty($userMessages);

        $firstUserMsg = array_values($userMessages)[0];
        $this->assertSame($actions[0]['prompt'], $firstUserMsg['content']);
    }

    public function test_send_quick_with_out_of_range_index_is_no_op(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(AssistantWidget::class)
            ->call('sendQuick', 999);

        // Input stays empty, no additional messages
        $component->assertSet('input', '');
        $messages     = $component->get('messages');
        $userMessages = array_filter($messages, fn ($m) => $m['sender'] === 'user');
        $this->assertEmpty($userMessages);
    }
}
