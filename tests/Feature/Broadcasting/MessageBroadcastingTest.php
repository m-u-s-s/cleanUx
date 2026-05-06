<?php

namespace Tests\Feature\Broadcasting;

use App\Events\MessageSent;
use App\Events\Presence\UserPresenceChanged;
use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskStatusChanged;
use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationAccount;
use App\Models\Task;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_sent_event_broadcasts_on_correct_private_channel(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);
        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Test channel',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $user->id,
        ]);
        $message = Message::create([
            'channel_id' => $channel->id,
            'user_id'    => $user->id,
            'content'    => 'hello team',
            'type'       => Message::TYPE_TEXT,
        ]);

        $event = new MessageSent($message);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-channel.' . $channel->id, $channels[0]->name);

        $payload = $event->broadcastWith();
        $this->assertSame($message->id, $payload['message_id']);
        $this->assertSame($channel->id, $payload['channel_id']);
        $this->assertSame($user->id, $payload['sender_id']);

        $this->assertSame('MessageSent', $event->broadcastAs());
    }

    public function test_task_assigned_event_broadcasts_on_user_org_and_channel_channels(): void
    {
        $org = OrganizationAccount::factory()->create();
        $assigner = User::factory()->create(['organization_account_id' => $org->id]);
        $assignee = User::factory()->create(['organization_account_id' => $org->id]);

        $task = new Task();
        $task->id = 99;
        $task->title = 'Préparer le devis';
        $task->priority = 'urgent';
        $task->status = 'pending';
        $task->organization_account_id = $org->id;
        $task->channel_id = 42;
        $task->assigned_to_user_id = $assignee->id;

        $event = new TaskAssigned($task, $assignee, $assigner->id);
        $channels = $event->broadcastOn();

        $names = array_map(fn ($c) => $c->name, $channels);
        $this->assertContains('private-user.' . $assignee->id, $names);
        $this->assertContains('private-presence-org.' . $org->id, $names);
        $this->assertContains('private-channel.42', $names);

        $payload = $event->broadcastWith();
        $this->assertSame(99, $payload['task_id']);
        $this->assertSame('Préparer le devis', $payload['title']);
        $this->assertSame('urgent', $payload['priority']);
        $this->assertSame($assignee->id, $payload['assigned_to']['id']);
        $this->assertSame($assigner->id, $payload['assigned_by']);
    }

    public function test_task_status_changed_broadcasts_with_status_diff(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);

        $task = new Task();
        $task->id = 7;
        $task->organization_account_id = $org->id;
        $task->assigned_to_user_id = $user->id;

        $event = new TaskStatusChanged($task, 'pending', 'in_progress', $user->id);
        $payload = $event->broadcastWith();

        $this->assertSame(7, $payload['task_id']);
        $this->assertSame('pending', $payload['previous_status']);
        $this->assertSame('in_progress', $payload['new_status']);
        $this->assertSame($user->id, $payload['changed_by']);

        $this->assertSame('TaskStatusChanged', $event->broadcastAs());
    }

    public function test_user_presence_changed_event_broadcasts_to_user_and_org(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);

        $event = new UserPresenceChanged(
            user: $user,
            status: UserPresenceChanged::STATUS_BUSY,
            customMessage: 'En réunion jusque 15h',
            organizationAccountId: $org->id,
        );

        $names = array_map(fn ($c) => $c->name, $event->broadcastOn());
        $this->assertContains('private-user.' . $user->id, $names);
        $this->assertContains('private-presence-org.' . $org->id, $names);

        $payload = $event->broadcastWith();
        $this->assertSame('busy', $payload['status']);
        $this->assertSame('En réunion jusque 15h', $payload['custom_message']);
        $this->assertSame($user->id, $payload['user_id']);
    }

    public function test_messagesent_can_be_dispatched_via_event_facade_under_fake(): void
    {
        Event::fake([MessageSent::class]);

        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['organization_account_id' => $org->id]);
        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Faked channel',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $user->id,
        ]);
        $message = Message::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'content' => 'fake hello',
            'type' => Message::TYPE_TEXT,
        ]);

        broadcast(new MessageSent($message));

        Event::assertDispatched(MessageSent::class, function ($e) use ($message) {
            return $e->message->id === $message->id;
        });
    }
}
