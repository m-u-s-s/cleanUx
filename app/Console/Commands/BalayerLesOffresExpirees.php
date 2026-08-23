<?php

namespace App\Console\Commands;

use App\Models\MissionAssignment;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** LE FILET SOUS LES OFFRES DE MISSION. */
class BalayerLesOffresExpirees extends Command
{
    protected $signature = 'dispatch:balayer-les-offres-expirees
                            {--grace=60 : Secondes de battement laissées au job différé avant de le doubler}
                            {--limit=200 : Bornes du balayage, pour ne pas monopoliser un worker}';

    protected $description = 'Expire et réescalade les offres de mission que le job différé n’a pas traitées.';

    public function handle(MissionDispatchService $service): int
    {
        // LE BATTEMENT ÉVITE DE DOUBLER LE JOB SUR SON PROPRE CRÉNEAU.
        $grace = max(0, (int) $this->option('grace'));
        $limite = max(1, (int) $this->option('limit'));

        $oubliees = MissionAssignment::query()
            ->where('assignment_status', 'assigned')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->subSeconds($grace))
            ->with('mission')
            ->orderBy('expires_at')
            ->limit($limite)
            ->get();

        if ($oubliees->isEmpty()) {
            $this->info('Offres expirées oubliées : 0');

            return self::SUCCESS;
        }

        $reescaladees = 0;
        $echecs = 0;

        foreach ($oubliees as $offre) {
            try {
                $service->expireAndEscalate($offre);
                $reescaladees++;
            } catch (\Throwable $e) {
                $echecs++;

                // UN ÉCHEC N'ARRÊTE PAS LE BALAYAGE.
                Log::error('[dispatch] réescalade impossible', [
                    'assignment_id' => $offre->id,
                    'raison' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            'Offres expirées oubliées : %d · réescaladées : %d · en échec : %d',
            $oubliees->count(),
            $reescaladees,
            $echecs,
        ));

        return self::SUCCESS;
    }
}
