<?php

namespace App\Console\Commands;

use App\Models\MissionExtra;
use App\Services\Missions\OnSite\MissionExtraService;
use Illuminate\Console\Command;

/** LA REPRISE QUI N'EXISTAIT PAS. */
class ReprendreLesPrelevementsDExtras extends Command
{
    protected $signature = 'extras:reprendre-les-prelevements
                            {--tentatives=3 : au-delà, le dossier passe à un humain}
                            {--age-heures=1 : on laisse retomber un échec passager avant de réessayer}';

    protected $description = 'Reprend les prélèvements de suppléments acceptés par le client mais jamais encaissés.';

    public function handle(MissionExtraService $service): int
    {
        $maxTentatives = max(1, (int) $this->option('tentatives'));
        $ageMinimum = now()->subHours(max(0, (int) $this->option('age-heures')));

        // On ne reprend QUE les `approved`.
        $enSouffrance = MissionExtra::query()
            ->where('status', MissionExtra::STATUS_APPROVED)
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $ageMinimum)
            ->with(['mission.booking.client', 'mission.booking.assignedProvider', 'mission.booking.employe'])
            ->get();

        $repris = 0;
        $abandonnes = 0;

        foreach ($enSouffrance as $extra) {
            $tentatives = (int) (($extra->metadata['tentatives_de_prelevement'] ?? 0));

            if ($tentatives >= $maxTentatives) {
                $abandonnes++;

                continue;
            }

            $extra->forceFill([
                'metadata' => array_merge((array) ($extra->metadata ?? []), [
                    'tentatives_de_prelevement' => $tentatives + 1,
                ]),
            ])->save();

            $service->reprendreLePrelevement($extra->refresh());

            if ($extra->refresh()->status === MissionExtra::STATUS_CHARGED) {
                $repris++;
            }
        }

        $this->info(sprintf(
            'Suppléments en souffrance : %d · encaissés : %d · à traiter à la main : %d',
            $enSouffrance->count(),
            $repris,
            $abandonnes,
        ));

        return self::SUCCESS;
    }
}
