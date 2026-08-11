<?php

namespace App\Events\Missions;

use App\Models\BroadcastEvent;
use App\Models\Mission;
use App\Models\MissionMedia;
use App\Realtime\Contracts\TracksBroadcastLedger;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Une photo vient d'être prise sur place — le client la voit apparaître sans rien faire.
 *
 * `ShouldBroadcastNow` et pas `ShouldBroadcast` : l'intérêt du cliché est qu'il arrive PENDANT
 * que le client se demande ce qui se passe chez lui. Passé par une file d'attente, il arriverait
 * après le prestataire.
 *
 * La charge ne porte PAS d'adresse de fichier. Une URL signée expire, et une URL diffusée à
 * quiconque écoute le canal survivrait à la fin de la mission dans les journaux du navigateur ;
 * l'application redemande la liste, qui vérifie l'accès à chaque appel.
 */
class MissionMediaAdded implements ShouldBroadcastNow, TracksBroadcastLedger
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Mission $mission,
        public MissionMedia $media,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('mission.'.$this->mission->id)];
    }

    public function broadcastAs(): string
    {
        return 'mission.media';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'mission_id' => $this->mission->id,
            'media_id' => $this->media->id,
            'media_type' => $this->media->media_type,
            'client_visible' => (bool) $this->media->client_visible,
            'taken_at' => $this->media->taken_at?->toIso8601String(),
        ];
    }

    public function broadcastCategory(): string
    {
        return BroadcastEvent::CATEGORY_MISSION_ONSITE;
    }

    public function broadcastIdempotencyKey(): ?string
    {
        return 'onsite:media:'.$this->media->id;
    }

    public function broadcastSourceType(): ?string
    {
        return Mission::class;
    }

    public function broadcastSourceId(): ?int
    {
        return $this->mission->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastLedgerPayload(): array
    {
        return $this->broadcastWith();
    }
}
