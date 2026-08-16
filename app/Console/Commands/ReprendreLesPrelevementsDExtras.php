<?php

namespace App\Console\Commands;

use App\Models\MissionExtra;
use App\Services\Missions\OnSite\MissionExtraService;
use Illuminate\Console\Command;

/**
 * LA REPRISE QUI N'EXISTAIT PAS.
 *
 * `MissionExtraService::prelever()` documentait depuis toujours que « l'extra reste `approved`, la
 * créance existe, et la reprise se fait plus tard ». Aucune reprise n'existait : ni commande, ni
 * job, ni tâche planifiée. Un supplément dont le prélèvement échouait restait `approved` pour
 * l'éternité — le client avait dit oui, le prestataire avait travaillé, et personne n'était payé.
 *
 * CE QUE CETTE COMMANDE NE FAIT PAS : elle ne relance pas indéfiniment. Une carte refusée trois
 * fois de suite ne deviendra pas valide à la quatrième, et chaque tentative laisse une trace chez
 * Stripe. Au-delà, l'affaire appartient à un humain — d'où la borne et le compte rendu.
 */
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

        /*
         * On ne reprend QUE les `approved`. Un `charged` a réussi, un `declined` a été refusé, un
         * `proposed` attend encore le client — les reprendre facturerait quelqu'un qui n'a rien
         * accepté.
         */
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
