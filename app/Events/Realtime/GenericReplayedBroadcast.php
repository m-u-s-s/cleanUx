<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event utilisé uniquement par le replay admin : ré-émet un payload archivé
 * sur le channel d'origine, sans avoir à reconstruire l'objet event original.
 *
 * Le `broadcastAs` est conservé pour que les listeners JS Echo le matchent
 * comme s'il s'agissait du push initial.
 */
class GenericReplayedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $channelName,
        public string $broadcastAsName,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        $name = $this->stripPrefix($this->channelName);

        if (str_starts_with($this->channelName, 'private-')) {
            return [new PrivateChannel($name)];
        }
        if (str_starts_with($this->channelName, 'presence-')) {
            return [new PresenceChannel($name)];
        }
        return [new Channel($name)];
    }

    public function broadcastAs(): string
    {
        return $this->broadcastAsName;
    }

    public function broadcastWith(): array
    {
        return $this->payload + ['replayed' => true];
    }

    protected function stripPrefix(string $channel): string
    {
        foreach (['private-', 'presence-'] as $prefix) {
            if (str_starts_with($channel, $prefix)) {
                return substr($channel, strlen($prefix));
            }
        }
        return $channel;
    }
}
