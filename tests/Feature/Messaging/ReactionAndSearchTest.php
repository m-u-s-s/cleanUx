<?php

namespace Tests\Feature\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\ReactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeContext(): array
    {
        $org    = OrganizationAccount::factory()->create();
        $author = User::factory()->create(['organization_account_id' => $org->id]);
        $other  = User::factory()->create(['organization_account_id' => $org->id]);

        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name'                    => 'reactions-test',
            'type'                    => Channel::TYPE_TEAM,
            'is_private'              => false,
            'created_by'              => $author->id,
        ]);
        $channel->members()->attach([$author->id, $other->id]);

        return ['channel' => $channel, 'author' => $author, 'other' => $other];
    }

    public function test_toggle_reaction_adds_then_removes(): void
    {
        $ctx     = $this->makeContext();
        $message = app(MessageService::class)->send($ctx['channel'], $ctx['author'], 'Hello');
        $svc     = app(ReactionService::class);

        $first = $svc->toggle($message, $ctx['author'], '👍');
        $this->assertSame('added', $first['action']);
        $this->assertSame(1, $message->reactions()->count());

        $second = $svc->toggle($message, $ctx['author'], '👍');
        $this->assertSame('removed', $second['action']);
        $this->assertSame(0, $message->reactions()->count());
    }

    public function test_two_users_can_react_with_same_emoji_independently(): void
    {
        $ctx     = $this->makeContext();
        $message = app(MessageService::class)->send($ctx['channel'], $ctx['author'], 'Hello');
        $svc     = app(ReactionService::class);

        $svc->toggle($message, $ctx['author'], '🎉');
        $svc->toggle($message, $ctx['other'],  '🎉');

        $this->assertSame(2, $message->reactions()->count());

        $summary = $svc->summarize($message, $ctx['author']);
        $this->assertCount(1, $summary);
        $this->assertSame('🎉', $summary[0]['emoji']);
        $this->assertSame(2,    $summary[0]['count']);
        $this->assertTrue($summary[0]['me']);
    }

    public function test_invalid_empty_emoji_throws(): void
    {
        $ctx     = $this->makeContext();
        $message = app(MessageService::class)->send($ctx['channel'], $ctx['author'], 'X');

        $this->expectException(\DomainException::class);
        app(ReactionService::class)->toggle($message, $ctx['author'], '');
    }

    public function test_search_messages_with_like_fallback(): void
    {
        $ctx = $this->makeContext();
        $svc = app(MessageService::class);

        $svc->send($ctx['channel'], $ctx['author'], 'On parle de la facture du client Beaurin');
        $svc->send($ctx['channel'], $ctx['author'], 'Random autre message');
        $svc->send($ctx['channel'], $ctx['author'], 'Beaurin a payé la facture aujourd\'hui');

        $found = Message::query()
            ->where('channel_id', $ctx['channel']->id)
            ->whereSearch('Beaurin')
            ->get();

        $this->assertSame(2, $found->count());
    }

    public function test_top_level_scope_excludes_replies(): void
    {
        $ctx = $this->makeContext();
        $svc = app(MessageService::class);

        $parent = $svc->send($ctx['channel'], $ctx['author'], 'parent');
        $svc->send($ctx['channel'], $ctx['author'], 'reply 1', parentId: $parent->id);
        $svc->send($ctx['channel'], $ctx['author'], 'reply 2', parentId: $parent->id);

        $topLevel = Message::where('channel_id', $ctx['channel']->id)->topLevel()->get();
        $this->assertSame(1, $topLevel->count());
        $this->assertSame($parent->id, $topLevel->first()->id);
    }
}
