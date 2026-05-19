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
 * Live ETA update for a mission — broadcast on private channel mission.{id}.
 */
class MissionLiveEta implements ShouldBroadcastNow, TracksBroadcastLedger
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Mission $mission,
        public int $etaMinutes,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $sequence = null,  // for idempotency dedup of identical pushes
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('mission.' . $this->mission->id)];
    }

    public function broadcastAs(): string
    {
        return 'mission.eta';
    }

    public function broadcastWith(): array
    {
        return [
            'mission_id' => $this->mission->id,
            'eta_minutes' => $this->etaMinutes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastCategory(): string
    {
        return \App\Models\BroadcastEvent::CATEGORY_MISSION_ETA;
    }

    public function broadcastIdempotencyKey(): ?string
    {
        if (! $this->sequence) {
            return null;
        }
        return 'eta:mission:' . $this->mission->id . ':' . $this->sequence;
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
