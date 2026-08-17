<?php

namespace App\Console\Commands;

use App\Models\MissionTimeSettlement;
use App\Services\Missions\HourlySettlementService;
use Illuminate\Console\Command;

/**
 * LA REPRISE DES RÈGLEMENTS DE TEMPS.
 *
 * Le prélèvement du temps supplémentaire part en échec doux à la clôture : un prestataire devant
 * la porte de son client ne doit pas voir son écran refuser une mission qu'il a faite parce qu'une
 * carte est momentanément indisponible. La créance reste alors constatée — et sans cette commande,
 * constatée pour l'éternité.
 *
 * C'est exactement le défaut qui avait été réparé sur les suppléments : un mécanisme qui documente
 * « la reprise se fait plus tard » sans qu'aucune reprise n'existe nulle part.
 *
 * ELLE NE RELANCE PAS INDÉFINIMENT. Une carte refusée trois fois ne deviendra pas valide à la
 * quatrième, et chaque tentative laisse une trace chez Stripe. Au-delà, l'affaire appartient à un
 * humain.
 */
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

        /*
         * `pending` ET `failed`, mais jamais `charged` ni `not_required`.
         *
         * `pending` couvre le cas où la clôture n'a même pas pu tenter le prélèvement — panne
         * réseau, exception attrapée en amont. `failed` est la tentative qui a échoué. Les deux
         * sont des créances ; les deux autres statuts sont des dossiers clos, et les rejouer
         * débiterait une seconde fois un dépassement déjà payé.
         */
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
