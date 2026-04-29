<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MissionPositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $missionId,
        public array $payload
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('mission.' . $this->missionId);
    }

    public function broadcastAs(): string
    {
        return 'position.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'mission_id' => $this->missionId,
            'data' => $this->payload,
        ];
    }
}