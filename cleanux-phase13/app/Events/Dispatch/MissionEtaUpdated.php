<?php

namespace App\Events\Dispatch;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 13 — Event broadcasté quand l'ETA d'une mission est mis à jour.
 *
 * Broadcasté sur le channel privé "mission.{id}" — le client (web ou app
 * mobile) écoute ce channel pour mettre à jour son écran de tracking.
 *
 * Émis automatiquement par MissionTrackingPointObserver après chaque ping GPS.
 */
class MissionEtaUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $missionId,
        public ?int $etaSeconds,
        public ?int $distanceMeters,
        public string $source,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('mission.' . $this->missionId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'mission_id'      => $this->missionId,
            'eta_seconds'     => $this->etaSeconds,
            'distance_meters' => $this->distanceMeters,
            'source'          => $this->source,
            'calculated_at'   => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MissionEtaUpdated';
    }
}
