<?php

namespace App\Events\Missions;

use App\Models\BroadcastEvent;
use App\Models\Mission;
use App\Models\MissionIncident;
use App\Realtime\Contracts\TracksBroadcastLedger;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un imprévu vient d'être signalé sur place.
 *
 * Le canal `mission.{id}` porte le client ET les prestataires assignés : le renfort qui arrive
 * dix minutes plus tard apprend ainsi que la porte est bloquée sans qu'on ait à l'appeler.
 *
 * La description N'EST PAS diffusée. Elle est libre, donc écrite par un humain pressé, et le canal
 * est plus large que le destinataire du signalement — le titre et la catégorie suffisent à faire
 * ouvrir la fiche, qui elle vérifie qui regarde.
 */
class MissionIncidentReported implements ShouldBroadcastNow, TracksBroadcastLedger
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Mission $mission,
        public MissionIncident $incident,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('mission.'.$this->mission->id)];
    }

    public function broadcastAs(): string
    {
        return 'mission.incident';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'mission_id' => $this->mission->id,
            'incident_id' => $this->incident->id,
            'incident_type' => $this->incident->incident_type,
            'severity' => $this->incident->severity,
            'title' => $this->incident->title,
            'has_photo' => $this->incident->mission_media_id !== null,
            'reported_at' => $this->incident->reported_at?->toIso8601String(),
        ];
    }

    public function broadcastCategory(): string
    {
        return BroadcastEvent::CATEGORY_MISSION_ONSITE;
    }

    public function broadcastIdempotencyKey(): ?string
    {
        return 'onsite:incident:'.$this->incident->id;
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
