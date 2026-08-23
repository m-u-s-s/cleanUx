<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** « Quelqu'un appelle dans ce canal. */
class CallStarted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Call $call) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('channel.'.$this->call->channel_id)];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->call->id,
            'channel_id' => $this->call->channel_id,
            'initiator_user_id' => $this->call->initiator_user_id,
            'type' => $this->call->type,
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallStarted';
    }
}
