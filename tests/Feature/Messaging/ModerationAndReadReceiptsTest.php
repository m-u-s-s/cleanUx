<?php

namespace Tests\Feature\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\ModerationAction;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Policies\ChannelPolicy;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\ModerationService;
use App\Services\Messaging\ReadReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationAndReadReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private function makeChannel(): array
    {
        $org    = OrganizationAccount::factory()->create();
        $owner  = User::factory()->create(['organization_account_id' => $org->id, 'name' => 'Owner']);
        $mod    = User::factory()->create(['organization_account_id' => $org->id, 'name' => 'Mod']);
        $member = User::factory()->create(['organization_account_id' => $org->id, 'name' => 'Member']);

        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name'        => 'general',
            'type'        => Channel::TYPE_TEAM,
            'is_private'  => false,
            'created_by'  => $owner->id,
        ]);

        $channel->members()->attach($owner->id,  ['role' => ChannelPolicy::ROLE_OWNER]);
        $channel->members()->attach($mod->id,    ['role' => ChannelPolicy::ROLE_MODERATOR]);
        $channel->members()->attach($member->id, ['role' => ChannelPolicy::ROLE_MEMBER]);

        return compact('org', 'owner', 'mod', 'member', 'channel');
    }

    // ──────────────────────────────────────────────────────
    // Policy basics
    // ──────────────────────────────────────────────────────

    public function test_member_can_post_in_open_channel(): void
    {
        $ctx = $this->makeChannel();
        $policy = app(ChannelPolicy::class);

        $this->assertTrue($policy->postMessage($ctx['member'], $ctx['channel']));
    }

    public function test_member_cannot_post_in_locked_channel_only_mod_can(): void
    {
        $ctx = $this->makeChannel();
        $ctx['channel']->update(['is_locked' => true]);
        $policy = app(ChannelPolicy::class);

        $this->assertFalse($policy->postMessage($ctx['member'], $ctx['channel']));
        $this->assertTrue($policy->postMessage($ctx['mod'],     $ctx['channel']));
        $this->assertTrue($policy->postMessage($ctx['owner'],   $ctx['channel']));
    }

    public function test_nobody_can_post_in_archived_channel(): void
    {
        $ctx = $this->makeChannel();
        $ctx['channel']->update(['is_archived' => true]);
        $policy = app(ChannelPolicy::class);

        $this->assertFalse($policy->postMessage($ctx['member'], $ctx['channel']));
        $this->assertFalse($policy->postMessage($ctx['mod'],    $ctx['channel']));
        $this->assertFalse($policy->postMessage($ctx['owner'],  $ctx['channel']));
    }

    public function test_author_can_delete_own_message(): void
    {
        $ctx = $this->makeChannel();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['member'], 'mine');
        $policy = app(ChannelPolicy::class);

        $this->assertTrue($policy->deleteMessage($ctx['member'], $msg));
    }

    public function test_member_cannot_delete_others_messages(): void
    {
        $ctx = $this->makeChannel();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['owner'], 'owner says hi');
        $policy = app(ChannelPolicy::class);

        $this->assertFalse($policy->deleteMessage($ctx['member'], $msg));
    }

    public function test_moderator_can_delete_any_message(): void
    {
        $ctx = $this->makeChannel();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['member'], 'inappropriate');
        $policy = app(ChannelPolicy::class);

        $this->assertTrue($policy->deleteMessage($ctx['mod'], $msg));
    }

    // ──────────────────────────────────────────────────────
    // ModerationService actions
    // ──────────────────────────────────────────────────────

    public function test_moderator_deletes_message_and_logs_action(): void
    {
        $ctx = $this->makeChannel();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['member'], 'spam');

        app(ModerationService::class)->deleteMessageAsModerator($ctx['mod'], $msg, 'Spam content');

        $this->assertSoftDeleted('messages', ['id' => $msg->id]);

        $msg->refresh();
        $this->assertSame($ctx['mod']->id, $msg->deleted_by);
        $this->assertSame('Spam content', $msg->deleted_reason);

        $this->assertDatabaseHas('moderation_actions', [
            'actor_user_id' => $ctx['mod']->id,
            'channel_id'    => $ctx['channel']->id,
            'message_id'    => $msg->id,
            'action_type'   => ModerationAction::TYPE_DELETE_MESSAGE,
        ]);
    }

    public function test_moderator_pins_and_unpins_message(): void
    {
        $ctx = $this->makeChannel();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['member'], 'pin me');

        app(ModerationService::class)->pinMessage($ctx['mod'], $msg);
        $msg->refresh();
        $this->assertTrue($msg->is_pinned);
        $this->assertSame($ctx['mod']->id, $msg->pinned_by);

        app(ModerationService::class)->unpinMessage($ctx['mod'], $msg);
        $msg->refresh();
        $this->assertFalse($msg->is_pinned);
    }

    public function test_member_cannot_pin_throws(): void
    {
        $ctx = $this->makeChannel();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['member'], 'try pin');

        $this->expectException(\DomainException::class);
        app(ModerationService::class)->pinMessage($ctx['member'], $msg);
    }

    public function test_owner_archives_channel(): void
    {
        $ctx = $this->makeChannel();
        app(ModerationService::class)->archiveChannel($ctx['owner'], $ctx['channel']);

        $ctx['channel']->refresh();
        $this->assertTrue($ctx['channel']->is_archived);
        $this->assertNotNull($ctx['channel']->archived_at);
        $this->assertSame($ctx['owner']->id, $ctx['channel']->archived_by);
    }

    public function test_moderator_cannot_archive_only_owner_can(): void
    {
        $ctx = $this->makeChannel();
        $this->expectException(\DomainException::class);
        app(ModerationService::class)->archiveChannel($ctx['mod'], $ctx['channel']);
    }

    public function test_moderator_kicks_member(): void
    {
        $ctx = $this->makeChannel();
        app(ModerationService::class)->kickMember($ctx['mod'], $ctx['channel'], $ctx['member'], 'inactive');

        $this->assertSame(
            0,
            $ctx['channel']->members()->where('user_id', $ctx['member']->id)->count()
        );
    }

    public function test_cannot_kick_owner(): void
    {
        $ctx = $this->makeChannel();
        $this->expectException(\DomainException::class);
        app(ModerationService::class)->kickMember($ctx['mod'], $ctx['channel'], $ctx['owner']);
    }

    public function test_owner_promotes_member_to_moderator(): void
    {
        $ctx = $this->makeChannel();
        app(ModerationService::class)->changeMemberRole(
            $ctx['owner'],
            $ctx['channel'],
            $ctx['member'],
            ChannelPolicy::ROLE_MODERATOR
        );

        $newRole = $ctx['channel']->members()->where('user_id', $ctx['member']->id)->first()->pivot->role;
        $this->assertSame(ChannelPolicy::ROLE_MODERATOR, $newRole);
    }

    // ──────────────────────────────────────────────────────
    // Read receipts
    // ──────────────────────────────────────────────────────

    public function test_unread_count_is_zero_when_member_has_read_latest(): void
    {
        $ctx = $this->makeChannel();
        $svc = app(MessageService::class);
        $rcv = app(ReadReceiptService::class);

        $svc->send($ctx['channel'], $ctx['owner'], 'msg 1');
        $svc->send($ctx['channel'], $ctx['owner'], 'msg 2');

        $rcv->markChannelAsRead($ctx['member'], $ctx['channel']);

        $this->assertSame(0, $rcv->unreadCount($ctx['member'], $ctx['channel']));
    }

    public function test_unread_count_increments_after_new_messages(): void
    {
        $ctx = $this->makeChannel();
        $svc = app(MessageService::class);
        $rcv = app(ReadReceiptService::class);

        $svc->send($ctx['channel'], $ctx['owner'], 'first');
        $rcv->markChannelAsRead($ctx['member'], $ctx['channel']);

        $svc->send($ctx['channel'], $ctx['owner'], 'second after read');
        $svc->send($ctx['channel'], $ctx['owner'], 'third after read');

        $this->assertSame(2, $rcv->unreadCount($ctx['member'], $ctx['channel']));
    }

    public function test_unread_count_excludes_own_messages(): void
    {
        $ctx = $this->makeChannel();
        $svc = app(MessageService::class);
        $rcv = app(ReadReceiptService::class);

        // Member envoie 3 messages mais ne se compte pas lui-même
        $svc->send($ctx['channel'], $ctx['member'], 'mine 1');
        $svc->send($ctx['channel'], $ctx['member'], 'mine 2');
        $svc->send($ctx['channel'], $ctx['member'], 'mine 3');

        $this->assertSame(0, $rcv->unreadCount($ctx['member'], $ctx['channel']));
    }
}
