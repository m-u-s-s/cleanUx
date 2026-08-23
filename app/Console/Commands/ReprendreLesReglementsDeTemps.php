<?php

namespace App\Console\Commands;

use App\Models\MissionTimeSettlement;
use App\Services\Missions\HourlySettlementService;
use Illuminate\Console\Command;

/** LA REPRISE DES RÈGLEMENTS DE TEMPS. */
class ReprendreLesReglementsDeTemps extends Command
{
    protected $signature = 'temps:reprendre-les-reglements
                            {--tentatives=3 : au-delà, le dossier passe à un humain}
                            {--age-minutes=30 : on laisse retomber un échec passager avant de réessayer}';

    protected $description = 'Reprend les prélèvements de temps supplémentaire constatés mais jamais encaissés.';

    public function handle(HourlySettlementService $service): int
    {
        $maxTentatives = max(1, (int) $this->option('tentatives'));
        $age = now()->subMinutes(max(0, (int) $this->option('age-minutes')));

        // `pending` ET `failed`, mais jamais `charged` ni `not_required`.
        $enSouffrance = MissionTimeSettlement::query()
            ->whereIn('status', [MissionTimeSettlement::STATUT_EN_ATTENTE, MissionTimeSettlement::STATUT_ECHOUE])
            ->where('amount_due_cents', '>', 0)
            ->where('updated_at', '<=', $age)
            ->with(['booking.client', 'booking.assignedProvider', 'booking.employe', 'mission'])
            ->get();

        $repris = 0;
        $abandonnes = 0;

        foreach ($enSouffrance as $reglement) {
            if ($reglement->attempts >= $maxTentatives) {
                $abandonnes++;

                continue;
            }

            $service->encaisser($reglement);

            if ($reglement->refresh()->status === MissionTimeSettlement::STATUT_ENCAISSE) {
                $repris++;
            }
        }

        $this->info(sprintf(
            'Règlements de temps en souffrance : %d · encaissés : %d · à traiter à la main : %d',
            $enSouffrance->count(),
            $repris,
            $abandonnes,
        ));

        return self::SUCCESS;
    }
}
