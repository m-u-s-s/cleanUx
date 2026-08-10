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

        // On CRÉE la manquante, on ne resynchronise pas l'existante. `syncFromRendezVous`
        // réécrit le statut de la mission avec sa valeur initiale (`assigned` ou `planned`) :
        // l'appeler pendant l'exécution ramènerait une mission démarrée à son point de départ,
        // effaçant sa progression à chaque sauvegarde de la réservation.
        if (Mission::query()->where('booking_id', $rendezVous->id)->exists()) {
            return;
        }

        app(MissionLifecycleService::class)->syncFromRendezVous($rendezVous);
    }
}
