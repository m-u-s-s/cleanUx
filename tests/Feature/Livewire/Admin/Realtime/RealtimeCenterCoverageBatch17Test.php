<?php

namespace Tests\Feature\Livewire\Admin\Realtime;

use App\Livewire\Admin\Realtime\RealtimeCenter;
use App\Models\BroadcastEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RealtimeCenterCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Null driver so broadcast() in replay() is a no-op that won't throw.
        config(['broadcasting.default' => 'null']);

        $this->admin = User::factory()->admin()->create();
    }

    private function seedEvent(array $overrides = []): BroadcastEvent
    {
        return BroadcastEvent::create(array_merge([
            'channel' => 'mission.1',
            'event_class' => 'App\\Events\\Realtime\\MissionLiveEta',
            'broadcast_as' => 'mission.eta',
            'audience' => BroadcastEvent::AUDIENCE_PER_CHANNEL,
            'audience_id' => 1,
            'category' => BroadcastEvent::CATEGORY_MISSION_ETA,
            'payload' => ['eta' => 12],
            'status' => BroadcastEvent::STATUS_SENT,
            'attempts' => 1,
            'queued_at' => now(),
        ], $overrides));
    }

    public function test_render_exposes_kpis_and_items(): void
    {
        $this->seedEvent(['status' => BroadcastEvent::STATUS_SENT, 'channel' => 'mission.1']);
        $this->seedEvent(['status' => BroadcastEvent::STATUS_FAILED, 'channel' => 'mission.2', 'failed_reason' => 'boom']);
        $this->seedEvent(['status' => BroadcastEvent::STATUS_QUEUED, 'channel' => 'mission.3']);

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->assertOk()
            ->assertViewHas('items')
            ->assertViewHas('kpis', function (array $kpis) {
                return $kpis['total_24h'] === 3
                    && $kpis['sent_24h'] === 1
                    && $kpis['failed_24h'] === 1
                    && $kpis['distinct_channels_24h'] === 3;
            });
    }

    public function test_category_filter_narrows_items(): void
    {
        $match = $this->seedEvent(['category' => BroadcastEvent::CATEGORY_CHAT, 'channel' => 'chat.1']);
        $miss = $this->seedEvent(['category' => BroadcastEvent::CATEGORY_POSITION, 'channel' => 'pos.1']);

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->set('filterCategory', BroadcastEvent::CATEGORY_CHAT)
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->contains($match->id)
                && ! $items->pluck('id')->contains($miss->id));
    }

    public function test_audience_filter_narrows_items(): void
    {
        $match = $this->seedEvent(['audience' => BroadcastEvent::AUDIENCE_PRESENCE, 'channel' => 'presence-a']);
        $miss = $this->seedEvent(['audience' => BroadcastEvent::AUDIENCE_PER_USER, 'channel' => 'user.5']);

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->set('filterAudience', BroadcastEvent::AUDIENCE_PRESENCE)
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->contains($match->id)
                && ! $items->pluck('id')->contains($miss->id));
    }

    public function test_status_filter_narrows_items(): void
    {
        $match = $this->seedEvent(['status' => BroadcastEvent::STATUS_FAILED, 'failed_reason' => 'x']);
        $miss = $this->seedEvent(['status' => BroadcastEvent::STATUS_SENT]);

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->set('filterStatus', BroadcastEvent::STATUS_FAILED)
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->contains($match->id)
                && ! $items->pluck('id')->contains($miss->id));
    }

    public function test_search_matches_channel_event_class_and_broadcast_as(): void
    {
        $byChannel = $this->seedEvent(['channel' => 'unique-channel-token']);
        $byClass = $this->seedEvent(['channel' => 'c.1', 'event_class' => 'App\\Events\\UniqueClassToken']);
        $byAlias = $this->seedEvent(['channel' => 'c.2', 'broadcast_as' => 'unique-alias-token']);
        $miss = $this->seedEvent(['channel' => 'c.3', 'event_class' => 'App\\Events\\Other', 'broadcast_as' => 'other']);

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->set('search', 'unique-channel-token')
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->contains($byChannel->id)
                && ! $items->pluck('id')->contains($miss->id));

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->set('search', 'UniqueClassToken')
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->contains($byClass->id));

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->set('search', 'unique-alias-token')
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->contains($byAlias->id));
    }

    public function test_replay_resends_event_and_dispatches_success_toast(): void
    {
        $event = $this->seedEvent([
            'status' => BroadcastEvent::STATUS_FAILED,
            'failed_reason' => 'previous failure',
            'attempts' => 1,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RealtimeCenter::class)
            ->call('replay', $event->id)
            ->assertDispatched('toast');

        $event->refresh();
        $this->assertSame(BroadcastEvent::STATUS_SENT, $event->status);
        $this->assertNull($event->failed_reason);
        $this->assertSame(2, $event->attempts);
    }
}
