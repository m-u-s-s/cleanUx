<?php

namespace App\Console\Commands;

use App\Services\FaceCheck\FaceCheckService;
use Illuminate\Console\Command;

/** L'ENTRETIEN DU CONTRÔLE FACIAL — ce que le temps doit faire tout seul. */
class FaceCheckMaintenance extends Command
{
    protected $signature = 'face-check:maintenance';

    protected $description = 'Ferme les contrôles faciaux restés ouverts au-delà de leur délai.';

    public function handle(FaceCheckService $service): int
    {
        $expires = $service->expireStale();

        $this->info("Contrôles faciaux expirés : {$expires}");

        return self::SUCCESS;
    }
}
