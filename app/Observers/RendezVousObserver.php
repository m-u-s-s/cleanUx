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

        // ON CRÉE LA MANQUANTE, ON NE RESYNCHRONISE PAS L'EXISTANTE.
        if (Mission::query()->where('booking_id', $rendezVous->id)->exists()) {
            return;
        }

        app(MissionLifecycleService::class)->syncFromRendezVous($rendezVous);
    }
}
