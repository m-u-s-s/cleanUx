<?php

namespace App\Events\Missions;

use App\Models\BroadcastEvent;
use App\Models\Mission;
use App\Models\MissionExtra;
use App\Realtime\Contracts\TracksBroadcastLedger;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UN SUPPLÉMENT VIENT D'ÊTRE PROPOSÉ, et le client doit pouvoir répondre TOUT DE SUITE.
 *
 * C'est ce qui sépare cette fonction d'un formulaire : le prestataire est sur place, la question se
 * pose maintenant, et une réponse qui arrive après son départ ne sert plus à rien. Le canal
 * `mission.{id}` est déjà autorisé et déjà écouté par l'écran de suivi du client — l'écran s'ouvre
 * donc sur la proposition sans qu'il ait à recharger.
 *
 * LE MONTANT VOYAGE, et c'est délibéré. Le canal est privé et restreint aux personnes de la
 * mission ; leur cacher le prix les obligerait à ouvrir la fiche pour savoir s'il faut s'en
 * occuper, ce qui est exactement le geste de trop quand on veut une réponse en un tap.
 */
class MissionExtraProposed implements ShouldBroadcastNow, TracksBroadcastLedger
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Mission $mission,
        public MissionExtra $extra,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('mission.'.$this->mission->id)];
    }

    public function broadcastAs(): string
    {
        return 'mission.extra';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'extra_id' => $this->extra->id,
            'mission_id' => $this->mission->id,
            'label' => $this->extra->label,
            'price_cents' => $this->extra->price_cents,
            'currency' => $this->extra->currency,
            'status' => $this->extra->status,
        ];
    }

    public function broadcastCategory(): string
    {
        return BroadcastEvent::CATEGORY_MISSION_ONSITE;
    }

    public function broadcastIdempotencyKey(): ?string
    {
        // Une file rejouée ne doit pas faire clignoter deux fois la même proposition.
        return 'mission:extra:'.$this->extra->id;
    }

    public function broadcastSourceType(): ?string
    {
        return MissionExtra::class;
    }

    public function broadcastSourceId(): ?int
    {
        return $this->extra->id;
    }

    /** @return array<string, mixed> */
    public function broadcastLedgerPayload(): array
    {
        return $this->broadcastWith();
    }
}
