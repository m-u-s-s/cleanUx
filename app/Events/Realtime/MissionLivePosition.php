<?php

namespace App\Events\Realtime;

use App\Models\Mission;
use App\Realtime\Contracts\TracksBroadcastLedger;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live provider GPS position pushed during a mission.
 * Broadcast on private channel mission.{id}.
 */
class MissionLivePosition implements ShouldBroadcastNow, TracksBroadcastLedger
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Mission $mission,
        public float $latitude,
        public float $longitude,
        public ?float $accuracyMeters = null,
        public ?float $headingDegrees = null,
        public ?int $providerUserId = null,
        public ?string $sequence = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('mission.' . $this->mission->id)];
    }

    public function broadcastAs(): string
    {
        return 'mission.position';
    }

    public function broadcastWith(): array
    {
        return [
            'mission_id' => $this->mission->id,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'accuracy_m' => $this->accuracyMeters,
            'heading' => $this->headingDegrees,
            'provider_user_id' => $this->providerUserId,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastCategory(): string
    {
        return \App\Models\BroadcastEvent::CATEGORY_POSITION;
    }

    public function broadcastIdempotencyKey(): ?string
    {
        if (! $this->sequence) {
            return null;
        }
        return 'position:mission:' . $this->mission->id . ':' . $this->sequence;
    }

    public function broadcastSourceType(): ?string
    {
        return Mission::class;
    }

    public function broadcastSourceId(): ?int
    {
        return $this->mission->id;
    }

    public function broadcastLedgerPayload(): array
    {
        return $this->broadcastWith();
    }
}
