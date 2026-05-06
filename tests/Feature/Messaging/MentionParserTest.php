<?php

namespace Tests\Feature\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Messaging\MentionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionParserTest extends TestCase
{
    use RefreshDatabase;

    private function setupChannel(array $userNames = ['Alice Martin', 'Bob Dupont']): array
    {
        $org = OrganizationAccount::factory()->create();
        $owner = User::factory()->create(['name' => 'Owner Test', 'organization_account_id' => $org->id]);

        $channel = Channel::create([
            'organization_account_id' => $org->id,
            'name'                    => 'general',
            'type'                    => Channel::TYPE_TEAM,
            'is_private'              => false,
            'created_by'              => $owner->id,
        ]);

        $users = [$owner];
        $channel->members()->attach($owner->id);

        foreach ($userNames as $name) {
            $u = User::factory()->create(['name' => $name, 'organization_account_id' => $org->id]);
            $channel->members()->attach($u->id);
            $users[] = $u;
        }

        return ['channel' => $channel, 'users' => $users, 'org' => $org];
    }

    public function test_resolves_simple_first_name_mention(): void
    {
        $ctx = $this->setupChannel(['Alice Martin']);
        $alice = $ctx['users'][1];

        $message = Message::create([
            'channel_id' => $ctx['channel']->id,
            'user_id'    => $ctx['users'][0]->id,
            'content'    => 'Salut @alice, comment ça va ?',
        ]);

        $resolved = app(MentionParser::class)->extractAndPersist($message);

        $this->assertCount(1, $resolved['users']);
        $this->assertSame($alice->id, $resolved['users'][0]->id);

        $this->assertDatabaseHas('message_mentions', [
            'message_id'        => $message->id,
            'mentioned_user_id' => $alice->id,
            'mention_type'      => 'user',
        ]);
    }

    public function test_resolves_quoted_full_name(): void
    {
        $ctx = $this->setupChannel(['Alice Martin']);
        $alice = $ctx['users'][1];

        $message = Message::create([
            'channel_id' => $ctx['channel']->id,
            'user_id'    => $ctx['users'][0]->id,
            'content'    => 'Hey @"alice martin" tu peux check ?',
        ]);

        $resolved = app(MentionParser::class)->extractAndPersist($message);

        $this->assertCount(1, $resolved['users']);
        $this->assertSame($alice->id, $resolved['users'][0]->id);
    }

    public function test_resolves_special_mentions_here_and_channel(): void
    {
        $ctx = $this->setupChannel();
        $message = Message::create([
            'channel_id' => $ctx['channel']->id,
            'user_id'    => $ctx['users'][0]->id,
            'content'    => 'Réunion dans 5 min @here, urgent @channel',
        ]);

        $resolved = app(MentionParser::class)->extractAndPersist($message);

        $this->assertContains('here',    $resolved['special']);
        $this->assertContains('channel', $resolved['special']);

        $this->assertDatabaseHas('message_mentions', [
            'message_id' => $message->id,
            'mention_type' => 'here',
        ]);
        $this->assertDatabaseHas('message_mentions', [
            'message_id' => $message->id,
            'mention_type' => 'channel',
        ]);
    }

    public function test_ignores_mention_of_user_not_in_channel(): void
    {
        $ctx = $this->setupChannel(['Alice Martin']);
        // Stranger n'est PAS membre du channel
        $stranger = User::factory()->create(['name' => 'Stranger Outsider']);

        $message = Message::create([
            'channel_id' => $ctx['channel']->id,
            'user_id'    => $ctx['users'][0]->id,
            'content'    => '@stranger je veux te parler',
        ]);

        $resolved = app(MentionParser::class)->extractAndPersist($message);

        $this->assertCount(0, $resolved['users']);
        $this->assertSame(0, MessageMention::where('mentioned_user_id', $stranger->id)->count());
    }

    public function test_resolves_multiple_unique_mentions(): void
    {
        $ctx = $this->setupChannel(['Alice Martin', 'Bob Dupont']);
        [, $alice, $bob] = $ctx['users'];

        $message = Message::create([
            'channel_id' => $ctx['channel']->id,
            'user_id'    => $ctx['users'][0]->id,
            'content'    => '@alice et @bob, on synchronise ?',
        ]);

        $resolved = app(MentionParser::class)->extractAndPersist($message);

        $this->assertCount(2, $resolved['users']);
        $ids = collect($resolved['users'])->pluck('id')->all();
        $this->assertContains($alice->id, $ids);
        $this->assertContains($bob->id,   $ids);
    }

    public function test_does_not_create_duplicate_mention_when_user_mentioned_twice(): void
    {
        $ctx = $this->setupChannel(['Alice Martin']);
        $alice = $ctx['users'][1];

        $message = Message::create([
            'channel_id' => $ctx['channel']->id,
            'user_id'    => $ctx['users'][0]->id,
            'content'    => '@alice et encore @alice pour insister',
        ]);

        app(MentionParser::class)->extractAndPersist($message);

        $this->assertSame(
            1,
            MessageMention::where([
                'message_id' => $message->id,
                'mentioned_user_id' => $alice->id,
            ])->count()
        );
    }
}
