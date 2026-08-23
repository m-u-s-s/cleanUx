<?php

namespace App\Events\Dispatch;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** L'offre n'est plus à prendre : ferme la modale, chez tout le monde. */
class MissionOfferWithdrawn implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $providerUserId,
        public int $assignmentId,
        /** taken | expired | cancelled */
        public string $reason = 'taken',
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->providerUserId)];
    }

    public function broadcastAs(): string
    {
        return 'MissionOfferWithdrawn';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'assignment_id' => $this->assignmentId,
            'reason' => $this->reason,
        ];
    }
}
