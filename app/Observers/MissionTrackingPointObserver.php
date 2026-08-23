<?php

namespace App\Observers;

use App\Events\Dispatch\MissionEtaUpdated;
use App\Models\MissionTrackingPoint;
use App\Services\Eta\EtaService;

/** Phase 13 — Observer sur MissionTrackingPoint. */
class MissionTrackingPointObserver
{
    public function __construct(
        protected EtaService $etaService,
    ) {}

    public function created(MissionTrackingPoint $point): void
    {
        $session = $point->trackingSession;
        if (! $session || ! $session->mission_id) {
            return;
        }

        $mission = $session->mission;
        if (! $mission) {
            return;
        }

        // Ne calculer l'ETA que pour les missions activement en route
        if (! in_array($mission->status, ['en_route', 'arrived'], true)) {
            return;
        }

        $eta = $this->etaService->computeForMission($mission);

        // Broadcast même si l'ETA est null (pour signaler l'update de position)
        event(new MissionEtaUpdated(
            missionId: $mission->id,
            etaSeconds: $eta['seconds'],
            distanceMeters: $eta['meters'],
            source: $eta['source'],
        ));
    }
}
