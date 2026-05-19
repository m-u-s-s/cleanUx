<?php

namespace Tests\Feature\Realtime;

use App\Events\Realtime\MissionLiveEta;
use App\Events\Realtime\MissionLivePosition;
use App\Events\Realtime\UserLiveNotification;
use App\Models\BroadcastEvent;
use App\Models\Mission;
use App\Models\User;
use App\Realtime\RealtimeBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeBroadcastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No real broadcaster wiring needed — broadcast() just calls event() under the null driver.
        config(['broadcasting.default' => 'null']);
    }

    public function test_publish_records_tracked_event_in_ledger(): void
    {
        $mission = Mission::factory()->create();

        $event = new MissionLiveEta(
            mission: $mission,
            etaMinutes: 12,
            latitude: 50.85,
            longitude: 4.35,
            sequence: 'seq-001',
        );

        $row = app(RealtimeBroadcastService::class)->publish($event);

        $this->assertNotNull($row);
        $this->assertSame('private-mission.' . $mission->id, $row->channel);
        $this->assertSame(MissionLiveEta::class, $row->event_class);
        $this->assertSame('mission.eta', $row->broadcast_as);
        $this->assertSame(BroadcastEvent::CATEGORY_MISSION_ETA, $row->category);
        $this->assertSame(BroadcastEvent::STATUS_SENT, $row->status);
        $this->assertSame((int) $mission->id, (int) $row->source_id);
        $this->assertSame(Mission::class, $row->source_type);
        $this->assertSame((int) $mission->id, (int) $row->audience_id);
    }

    public function test_publish_is_idempotent_with_same_sequence(): void
    {
        $mission = Mission::factory()->create();

        $svc = app(RealtimeBroadcastService::class);

        $a = $svc->publish(new MissionLiveEta($mission, 10, sequence: 's1'));
        $b = $svc->publish(new MissionLiveEta($mission, 20, sequence: 's1'));

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, BroadcastEvent::count());
    }

    public function test_publish_without_sequence_creates_distinct_rows(): void
    {
        $mission = Mission::factory()->create();

        $svc = app(RealtimeBroadcastService::class);
        $svc->publish(new MissionLiveEta($mission, 10));
        $svc->publish(new MissionLiveEta($mission, 20));

        $this->assertSame(2, BroadcastEvent::count());
    }

    public function test_publish_position_records_with_position_category(): void
    {
        $mission = Mission::factory()->create();

        $row = app(RealtimeBroadcastService::class)->publish(new MissionLivePosition(
            mission: $mission,
            latitude: 50.85,
            longitude: 4.35,
            sequence: 'pos1',
        ));

        $this->assertSame(BroadcastEvent::CATEGORY_POSITION, $row->category);
        $this->assertSame('mission.position', $row->broadcast_as);
    }

    public function test_publish_user_notification_uses_per_user_audience(): void
    {
        $user = User::factory()->client()->create();

        $row = app(RealtimeBroadcastService::class)->publish(new UserLiveNotification(
            user: $user,
            type: 'booking.confirmed',
            title: 'Confirmation',
            body: 'Votre RDV est confirmé.',
            data: ['booking_id' => 42],
            idempotencyKey: 'notif:user:' . $user->id . ':booking-confirmed:42',
        ));

        $this->assertSame('private-user.' . $user->id, $row->channel);
        $this->assertSame(BroadcastEvent::AUDIENCE_PER_USER, $row->audience);
        $this->assertSame((int) $user->id, (int) $row->audience_id);
        $this->assertSame(BroadcastEvent::CATEGORY_NOTIFICATION, $row->category);
    }

    public function test_publish_untracked_event_returns_null_no_ledger(): void
    {
        $event = new class implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow {
            public function broadcastOn(): array { return [new \Illuminate\Broadcasting\Channel('public.test')]; }
        };

        $result = app(RealtimeBroadcastService::class)->publish($event);

        $this->assertNull($result);
        $this->assertSame(0, BroadcastEvent::count());
    }

    public function test_replay_marks_event_sent_again(): void
    {
        $mission = Mission::factory()->create();

        $row = app(RealtimeBroadcastService::class)->publish(new MissionLiveEta($mission, 5, sequence: 'rep1'));

        // Force into failed state then replay
        $row->forceFill([
            'status' => BroadcastEvent::STATUS_FAILED,
            'failed_reason' => 'simulated',
            'failed_at' => now(),
        ])->save();

        $ok = app(RealtimeBroadcastService::class)->replay($row);

        $this->assertTrue($ok);
        $row->refresh();
        $this->assertSame(BroadcastEvent::STATUS_SENT, $row->status);
        $this->assertGreaterThan(1, $row->attempts);
    }

    public function test_replay_works_on_archived_payload_without_reconstructing_event(): void
    {
        $row = BroadcastEvent::create([
            'channel' => 'private-mission.99',
            'event_class' => 'App\\Events\\Realtime\\NoLongerExists',
            'broadcast_as' => 'mission.eta',
            'audience' => BroadcastEvent::AUDIENCE_PER_CHANNEL,
            'audience_id' => 99,
            'category' => BroadcastEvent::CATEGORY_MISSION_ETA,
            'payload' => ['eta_minutes' => 7],
            'status' => BroadcastEvent::STATUS_FAILED,
            'queued_at' => now(),
        ]);

        $ok = app(RealtimeBroadcastService::class)->replay($row);

        $this->assertTrue($ok);
        $row->refresh();
        $this->assertSame(BroadcastEvent::STATUS_SENT, $row->status);
    }
}
