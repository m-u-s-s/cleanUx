<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * « Quelqu'un appelle dans ce canal. »
 *
 * DIFFUSÉ SUR `channel.{id}`, le canal déjà autorisé et fonctionnel : l'autorisation vérifie
 * l'appartenance au fil, ce qui est exactement la population qui doit voir la bannière. Ouvrir un
 * canal de diffusion dédié aux appels aurait demandé une seconde règle d'autorisation, vouée à
 * diverger de la première.
 *
 * LA CHARGE UTILE NE PORTE PAS LE JETON. Un jeton d'accès diffusé à tous les membres du canal
 * donnerait à chacun le droit d'entrer dans la salle sans avoir décroché — chacun demande le sien.
 */
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
