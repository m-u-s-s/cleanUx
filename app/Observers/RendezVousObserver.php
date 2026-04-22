<?php

namespace App\Observers;

use App\Models\RendezVous;
use App\Services\Missions\MissionLifecycleService;

class RendezVousObserver
{
    public function saved(RendezVous $rendezVous): void
    {
        if ($rendezVous->status !== 'confirme') {
            return;
        }

        app(MissionLifecycleService::class)->syncFromRendezVous($rendezVous);
    }
}