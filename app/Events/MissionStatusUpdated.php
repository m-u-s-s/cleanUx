<?php

namespace App\Events;

use App\Models\Mission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MissionStatusUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Mission $mission)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('mission.' . $this->mission->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'mission_id'  => $this->mission->id,
            'status'      => $this->mission->status,
            'updated_at'  => $this->mission->updated_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MissionStatusUpdated';
    }
}
