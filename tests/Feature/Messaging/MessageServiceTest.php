<?php

namespace Tests\Feature\Messaging;

use App\Events\MessageSent;
use App\Events\Messaging\MessageDeleted;
use App\Events\Messaging\MessageEdited;
use App\Events\Messaging\UserMentioned;
use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Notifications\MentionedInMessageNotification;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeChannelWithUsers(int $extraMembers = 1): array
    {
        $org    = OrganizationAccount::factory()->create();
        $author = User::factory()->create(['organization_account_id' => $org->id]);

        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name'                    => 'team-test',
            'type'                    => Channel::TYPE_TEAM,
            'is_private'              => false,
            'created_by'              => $author->id,
        ]);
        $channel->members()->attach($author->id);

        $others = [];
        for ($i = 0; $i < $extraMembers; $i++) {
            $u = User::factory()->create([
                'organization_account_id' => $org->id,
                'name' => "Member{$i} Test",
            ]);
            $channel->members()->attach($u->id);
            $others[] = $u;
        }

        return ['channel' => $channel, 'author' => $author, 'others' => $others];
    }

    public function test_send_creates_message_and_broadcasts(): void
    {
        Event::fake([MessageSent::class]);

        $ctx = $this->makeChannelWithUsers();

        $message = app(MessageService::class)->send(
            channel: $ctx['channel'],
            sender:  $ctx['author'],
            content: 'Salut équipe',
        );

        $this->assertDatabaseHas('messages', [
            'id'         => $message->id,
            'channel_id' => $ctx['channel']->id,
            'user_id'    => $ctx['author']->id,
            'content'    => 'Salut équipe',
            'parent_id'  => null,
        ]);

        Event::assertDispatched(MessageSent::class, fn ($e) => $e->message->id === $message->id);
    }

    public function test_send_with_parent_id_increments_thread_replies_count(): void
    {
        $ctx    = $this->makeChannelWithUsers();
        $svc    = app(MessageService::class);

        $parent = $svc->send($ctx['channel'], $ctx['author'], 'Question initiale');

        $this->assertSame(0, $parent->replies_count);

        $reply1 = $svc->send($ctx['channel'], $ctx['author'], 'Réponse 1', parentId: $parent->id);
        $reply2 = $svc->send($ctx['channel'], $ctx['author'], 'Réponse 2', parentId: $parent->id);

        $parent->refresh();
        $this->assertSame(2, $parent->replies_count);
        $this->assertNotNull($parent->last_reply_at);
        $this->assertGreaterThanOrEqual(
            $reply1->created_at->getTimestamp(),
            $parent->last_reply_at->getTimestamp()
        );
    }

    public function test_send_with_mention_dispatches_notification_and_user_mentioned_event(): void
    {
        Notification::fake();
        Event::fake([UserMentioned::class, MessageSent::class]);

        $ctx    = $this->makeChannelWithUsers(1);
        $bob    = $ctx['others'][0];
        // bob s'appelle "Member0 Test" → on le mentionne par "member0"
        $msg = app(MessageService::class)->send(
            $ctx['channel'],
            $ctx['author'],
            "@member0 peux-tu valider ?",
        );

        Notification::assertSentTo($bob, MentionedInMessageNotification::class);
        Event::assertDispatched(UserMentioned::class, fn ($e) => $e->mentionedUser->id === $bob->id);
    }

    public function test_edit_only_allowed_for_author(): void
    {
        Event::fake([MessageEdited::class]);

        $ctx     = $this->makeChannelWithUsers(1);
        $author  = $ctx['author'];
        $bob     = $ctx['others'][0];

        $message = app(MessageService::class)->send($ctx['channel'], $author, 'original');

        $this->expectException(\DomainException::class);
        app(MessageService::class)->edit($message, $bob, 'tentative pirate');
    }

    public function test_edit_updates_content_and_broadcasts(): void
    {
        Event::fake([MessageEdited::class]);

        $ctx = $this->makeChannelWithUsers();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['author'], 'original');

        $edited = app(MessageService::class)->edit($msg, $ctx['author'], 'corrigé');

        $this->assertSame('corrigé', $edited->content);
        $this->assertNotNull($edited->edited_at);

        Event::assertDispatched(MessageEdited::class, fn ($e) => $e->message->id === $msg->id);
    }

    public function test_delete_soft_deletes_and_broadcasts(): void
    {
        Event::fake([MessageDeleted::class]);

        $ctx = $this->makeChannelWithUsers();
        $msg = app(MessageService::class)->send($ctx['channel'], $ctx['author'], 'à supprimer');

        app(MessageService::class)->delete($msg, $ctx['author']);

        $this->assertSoftDeleted('messages', ['id' => $msg->id]);
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_deleting_thread_reply_decrements_parent_replies_count(): void
    {
        $ctx = $this->makeChannelWithUsers();
        $svc = app(MessageService::class);

        $parent = $svc->send($ctx['channel'], $ctx['author'], 'parent');
        $reply  = $svc->send($ctx['channel'], $ctx['author'], 'reply', parentId: $parent->id);

        $parent->refresh();
        $this->assertSame(1, $parent->replies_count);

        $svc->delete($reply, $ctx['author']);

        $parent->refresh();
        $this->assertSame(0, $parent->replies_count);
    }
}
