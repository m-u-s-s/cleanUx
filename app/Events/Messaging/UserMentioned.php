<?php

namespace App\Events\Messaging;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffuse l'événement "tu viens d'être mentionné" sur le canal personnel
 * de l'utilisateur, pour faire apparaître un toast/badge instantanément
 * même s'il n'est pas dans le channel actif.
 */
class UserMentioned implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Message $message,
        public User $mentionedUser,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->mentionedUser->id),
        ];
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'message_id'   => $this->message->id,
            'channel_id'   => $this->message->channel_id,
            'channel_name' => $this->message->channel?->name,
            'sender_id'    => $sender?->id,
            'sender_name'  => $sender?->name,
            'preview'      => str($this->message->content)->limit(140)->toString(),
            'created_at'   => $this->message->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserMentioned';
    }
}
