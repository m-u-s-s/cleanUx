<?php

namespace App\Events\Dispatch;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** L'offre qui fait apparaître la modale, poussée sur le canal personnel du prestataire. */
class MissionOfferPushed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $offer
     */
    public function __construct(
        public int $providerUserId,
        public array $offer,
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->providerUserId)];
    }

    public function broadcastAs(): string
    {
        return 'MissionOfferPushed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['offer' => $this->offer];
    }
}
