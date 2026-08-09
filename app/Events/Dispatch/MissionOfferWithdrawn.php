<?php

namespace App\Events\Dispatch;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * L'offre n'est plus à prendre : ferme la modale, chez tout le monde.
 *
 * Indispensable À CAUSE de la dernière vague, qui propose la même course à plusieurs prestataires
 * en même temps. Sans ce message, les perdants gardent une modale vivante et un compte à rebours
 * qui tourne sur une course déjà partie — ils appuient sur « Accepter », reçoivent une erreur, et
 * apprennent à ne plus croire les offres.
 */
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
