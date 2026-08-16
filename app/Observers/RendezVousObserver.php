<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use App\Support\Domain\BookingStatus;

class RendezVousObserver
{
    public function saved(Booking $rendezVous): void
    {
        $status = $rendezVous->status;

        if ($status === BookingStatus::CONFIRME) {
            app(MissionLifecycleService::class)->syncFromRendezVous($rendezVous);

            return;
        }

        // Une réservation peut atteindre l'exécution sans être passée par `confirme` — c'est le
        // cas du flux ASAP. Elle n'obtenait alors aucune mission et restait invisible dans
        // l'application prestataire : ni mise en route, ni arrivée, ni codes, ni clôture.
        if (! in_array($status, [BookingStatus::EN_ROUTE, BookingStatus::SUR_PLACE], true)) {
            return;
        }

        /*
         * ON CRÉE LA MANQUANTE, ON NE RESYNCHRONISE PAS L'EXISTANTE.
         *
         * Ce garde-fou protégeait la progression de la mission : la synchronisation réécrivait son
         * statut à sa valeur initiale, et l'appeler pendant l'exécution ramenait une mission
         * démarrée à son point de départ. Cette cause est traitée à la racine — voir
         * `MissionFromRendezVousSyncService::statutASynchroniser()` — et ce garde-fou n'est donc
         * plus ce qui préserve la justesse.
         *
         * Il reste, pour son COÛT : une resynchronisation complète géocode l'adresse, reconstruit
         * la checklist, arme le SLA et tente une auto-assignation. Rien de tout cela n'a de sens
         * pour une mission déjà en route.
         */
        if (Mission::query()->where('booking_id', $rendezVous->id)->exists()) {
            return;
        }

        app(MissionLifecycleService::class)->syncFromRendezVous($rendezVous);
    }
}
