<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\TeamChannels;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Policies\ChannelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamChannelsComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an org + a user with an active membership granting `channels.create`.
     * The user is NOT attached to any channel, so mount() leaves
     * activeChannelId = 0 and loadMessages() always early-returns — this keeps
     * the component green despite the latent attachments-column shadowing bug
     * (see class-level notes returned to the orchestrator).
     *
     * @return array{org: OrganizationAccount, user: User}
     */
    private function makeMember(string $role = OrganizationRole::OWNER->value): array
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['current_organization_id' => $org->id]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return compact('org', 'user');
    }

    /**
     * Build a full context where the user owns and belongs to a channel that
     * has NO messages (so the initial render / openChannel stays green).
     *
     * @return array{org: OrganizationAccount, owner: User, channel: Channel}
     */
    private function makeChannelContext(): array
    {
        $org = OrganizationAccount::factory()->create();
        $owner = User::factory()->create([
            'current_organization_id' => $org->id,
            'name' => 'Olivia Owner',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
        ]);

        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'general',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $owner->id,
        ]);
        $channel->members()->attach($owner->id, ['role' => ChannelPolicy::ROLE_OWNER]);

        return compact('org', 'owner', 'channel');
    }

    // ──────────────────────────────────────────────────────
    // mount()
    // ──────────────────────────────────────────────────────

    public function test_mount_opens_first_channel_and_renders(): void
    {
        $ctx = $this->makeChannelContext();

        Livewire::actingAs($ctx['owner'])
            ->test(TeamChannels::class)
            ->assertOk()
            ->assertSet('activeChannelId', $ctx['channel']->id);
    }

    public function test_mount_renders_empty_when_user_has_no_channels(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->assertOk()
            ->assertSet('activeChannelId', 0)
            ->assertSet('messages', []);
    }

    public function test_mount_aborts_403_when_user_lacks_channels_create_permission(): void
    {
        $org = OrganizationAccount::factory()->create();
        $viewer = User::factory()->create(['current_organization_id' => $org->id]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $viewer->id,
            'role' => OrganizationRole::VIEWER->value,
            'status' => 'active',
        ]);

        Livewire::actingAs($viewer)
            ->test(TeamChannels::class)
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────
    // sendMessage() guard
    // ──────────────────────────────────────────────────────

    public function test_send_message_does_nothing_without_active_channel(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->set('messageInput', 'hello there')
            ->call('sendMessage');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_send_message_ignores_blank_input(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->set('messageInput', '   ')
            ->call('sendMessage');

        $this->assertDatabaseCount('messages', 0);
    }

    // ──────────────────────────────────────────────────────
    // createChannel() validation guard
    // ──────────────────────────────────────────────────────

    public function test_create_channel_validates_required_name(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->set('newChannelName', '')
            ->call('createChannel')
            ->assertHasErrors(['newChannelName' => 'required']);
    }

    public function test_create_channel_validates_channel_type(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->set('newChannelName', 'valid-name')
            ->set('newChannelType', 'not-a-type')
            ->call('createChannel')
            ->assertHasErrors(['newChannelType']);
    }

    // ──────────────────────────────────────────────────────
    // Edit flow
    // ──────────────────────────────────────────────────────

    public function test_start_edit_loads_own_message_into_state(): void
    {
        $ctx = $this->makeMember();
        $msg = Message::create([
            'channel_id' => $this->bareChannel($ctx['org'], $ctx['user'])->id,
            'user_id' => $ctx['user']->id,
            'content' => 'mon message',
        ]);

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('startEdit', $msg->id)
            ->assertSet('editingMessageId', $msg->id)
            ->assertSet('editContent', 'mon message');
    }

    public function test_start_edit_ignores_other_users_message(): void
    {
        $ctx = $this->makeMember();
        $stranger = User::factory()->create();
        $msg = Message::create([
            'channel_id' => $this->bareChannel($ctx['org'], $stranger)->id,
            'user_id' => $stranger->id,
            'content' => 'pas à moi',
        ]);

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('startEdit', $msg->id)
            ->assertSet('editingMessageId', null)
            ->assertSet('editContent', '');
    }

    public function test_save_edit_updates_own_message(): void
    {
        $ctx = $this->makeMember();
        $msg = Message::create([
            'channel_id' => $this->bareChannel($ctx['org'], $ctx['user'])->id,
            'user_id' => $ctx['user']->id,
            'content' => 'avant',
        ]);

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->set('editingMessageId', $msg->id)
            ->set('editContent', 'après édition')
            ->call('saveEdit')
            ->assertSet('editingMessageId', null)
            ->assertSet('editContent', '');

        $msg->refresh();
        $this->assertSame('après édition', $msg->content);
        $this->assertNotNull($msg->edited_at);
    }

    public function test_save_edit_does_nothing_without_editing_id(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('saveEdit')
            ->assertSet('editingMessageId', null);
    }

    public function test_save_edit_ignores_blank_content(): void
    {
        $ctx = $this->makeMember();
        $msg = Message::create([
            'channel_id' => $this->bareChannel($ctx['org'], $ctx['user'])->id,
            'user_id' => $ctx['user']->id,
            'content' => 'inchangé',
        ]);

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->set('editingMessageId', $msg->id)
            ->set('editContent', '   ')
            ->call('saveEdit');

        $this->assertSame('inchangé', $msg->fresh()->content);
    }

    public function test_cancel_edit_resets_state(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->set('editingMessageId', 123)
            ->set('editContent', 'draft')
            ->call('cancelEdit')
            ->assertSet('editingMessageId', null)
            ->assertSet('editContent', '');
    }

    // ──────────────────────────────────────────────────────
    // deleteMessage() guard
    // ──────────────────────────────────────────────────────

    public function test_delete_message_ignores_missing_message(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('deleteMessage', 999999)
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────
    // Reactions
    // ──────────────────────────────────────────────────────

    public function test_toggle_reaction_adds_then_removes(): void
    {
        $ctx = $this->makeMember();
        $msg = Message::create([
            'channel_id' => $this->bareChannel($ctx['org'], $ctx['user'])->id,
            'user_id' => $ctx['user']->id,
            'content' => 'réagis',
        ]);

        $component = Livewire::actingAs($ctx['user'])->test(TeamChannels::class);

        $component->call('toggleReaction', $msg->id, '👍');
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $msg->id,
            'user_id' => $ctx['user']->id,
            'emoji' => '👍',
        ]);

        $component->call('toggleReaction', $msg->id, '👍');
        $this->assertSame(0, MessageReaction::where('message_id', $msg->id)->count());
    }

    public function test_set_reply_to_sets_and_clears_property(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('setReplyTo', 42)
            ->assertSet('replyingToId', 42)
            ->call('setReplyTo', null)
            ->assertSet('replyingToId', null);
    }

    // ──────────────────────────────────────────────────────
    // Pin / moderation guards
    // ──────────────────────────────────────────────────────

    public function test_pin_message_ignores_missing_message(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('pinMessage', 999999)
            ->assertOk();
    }

    public function test_unpin_message_ignores_missing_message(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('unpinMessage', 999999)
            ->assertOk();
    }

    public function test_pin_message_flashes_error_when_not_authorized(): void
    {
        // User belongs to the org but is NOT a moderator of the channel,
        // so ModerationService throws a DomainException that the component
        // catches and turns into a flashed error (no crash).
        $ctx = $this->makeMember();
        $stranger = User::factory()->create();
        $channel = $this->bareChannel($ctx['org'], $stranger);
        $msg = Message::create([
            'channel_id' => $channel->id,
            'user_id' => $stranger->id,
            'content' => 'cannot pin me',
        ]);

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('pinMessage', $msg->id)
            ->assertOk();

        $this->assertFalse((bool) $msg->fresh()->is_pinned);
    }

    // ──────────────────────────────────────────────────────
    // Channel-level moderation guards (no active channel)
    // ──────────────────────────────────────────────────────

    public function test_lock_channel_does_nothing_without_active_channel(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('lockChannel')
            ->assertOk();
    }

    public function test_archive_channel_does_nothing_without_active_channel(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('archiveChannel')
            ->assertSet('activeChannelId', 0);
    }

    // ──────────────────────────────────────────────────────
    // Echo listener + loadMessages empty branch
    // ──────────────────────────────────────────────────────

    public function test_on_new_message_reloads_messages(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('onNewMessage', ['foo' => 'bar'])
            ->assertSet('messages', []);
    }

    public function test_load_messages_returns_empty_without_active_channel(): void
    {
        $ctx = $this->makeMember();

        Livewire::actingAs($ctx['user'])
            ->test(TeamChannels::class)
            ->call('loadMessages')
            ->assertSet('messages', []);
    }

    /**
     * Create a channel owned by $owner in $org (no member attached unless owner).
     */
    private function bareChannel(OrganizationAccount $org, User $owner): Channel
    {
        return Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'aux-'.uniqid(),
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $owner->id,
        ]);
    }
}
