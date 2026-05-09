<?php

namespace App\Observers;

use App\Events\Dispatch\MissionEtaUpdated;
use App\Models\MissionTrackingPoint;
use App\Services\Eta\EtaService;

/**
 * Phase 13 — Observer sur MissionTrackingPoint.
 *
 * À chaque nouveau point GPS reçu, recalcule l'ETA pour la mission et
 * broadcast le résultat sur le channel privé de la mission.
 *
 * Throttle implicite : si 2 points arrivent à <60s d'intervalle, le cache du
 * service evite l'appel Google. Mais l'event est quand même broadcasté pour
 * rafraîchir la position côté client.
 *
 * Bind dans AppServiceProvider::boot() :
 *   MissionTrackingPoint::observe(MissionTrackingPointObserver::class);
 */
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
            missionId:      $mission->id,
            etaSeconds:     $eta['seconds'],
            distanceMeters: $eta['meters'],
            source:         $eta['source'],
        ));
    }
}
