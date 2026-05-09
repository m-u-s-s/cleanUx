<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\Missions\MissionLifecycleService;

class RendezVousObserver
{
    public function saved(Booking $rendezVous): void
    {
        if ($rendezVous->status !== 'confirme') {
            return;
        }

        app(MissionLifecycleService::class)->syncFromRendezVous($rendezVous);
    }
}