<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MissionStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $missionId,
        public string $status,
        public array $payload = []
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('mission.' . $this->missionId);
    }

    public function broadcastAs(): string
    {
        return 'status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'mission_id' => $this->missionId,
            'status' => $this->status,
            'data' => $this->payload,
        ];
    }
}