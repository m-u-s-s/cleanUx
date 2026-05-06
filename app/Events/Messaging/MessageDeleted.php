<?php

namespace App\Events\Messaging;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel.' . $this->message->channel_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'  => $this->message->id,
            'channel_id'  => $this->message->channel_id,
            'parent_id'   => $this->message->parent_id,
            'deleted_at'  => $this->message->deleted_at?->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageDeleted';
    }
}
