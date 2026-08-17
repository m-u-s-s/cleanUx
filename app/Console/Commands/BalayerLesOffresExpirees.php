<?php

namespace App\Console\Commands;

use App\Models\MissionAssignment;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * LE FILET SOUS LES OFFRES DE MISSION.
 *
 * L'expiration d'une offre repose sur `EscalateMissionAssignmentJob`, mis en file AVEC UN DÉLAI
 * jusqu'à `expires_at` et déclaré `tries = 1`. C'est efficace et c'est fragile : un worker
 * redémarré pendant que le job attend, une file vidée, un échec unique sur un hoquet de base, et
 * plus rien ne se déclenche jamais.
 *
 * CE QUE ÇA DONNE QUAND ÇA ARRIVE. L'offre reste `assigned` indéfiniment. Le prestataire ne répond
 * pas — il ne l'a peut-être même pas vue. La mission n'est JAMAIS proposée au suivant, parce que
 * l'escalade est précisément ce qui devait la relancer. Et le client attend quelqu'un qui ne
 * viendra pas, sans que rien nulle part ne soit en erreur.
 *
 * ON REJOUE LE MÊME CHEMIN, pas un chemin parallèle. `expireAndEscalate()` est appelé tel quel :
 * il se garde lui-même d'une offre déjà acceptée, déjà refusée, ou pas encore expirée, et il
 * enchaîne sur le prestataire suivant. Réécrire l'expiration ici en ferait une seconde version qui
 * divergerait — et c'est celle du balayage, jamais relue, qui déciderait du sort des missions
 * oubliées.
 *
 * LE BALAYAGE N'EST PAS LE MÉCANISME PRINCIPAL et ne doit pas le devenir : le job différé reste
 * instantané à la seconde près, ce qu'une tâche planifiée ne sera jamais. Celle-ci ne rattrape que
 * ce qui est passé au travers.
 */
class BalayerLesOffresExpirees extends Command
{
    protected $signature = 'dispatch:balayer-les-offres-expirees
                            {--grace=60 : Secondes de battement laissées au job différé avant de le doubler}
                            {--limit=200 : Bornes du balayage, pour ne pas monopoliser un worker}';

    protected $description = 'Expire et réescalade les offres de mission que le job différé n’a pas traitées.';

    public function handle(MissionDispatchService $service): int
    {
        /*
         * LE BATTEMENT ÉVITE DE DOUBLER LE JOB SUR SON PROPRE CRÉNEAU.
         *
         * Sans lui, le balayage et le job différé pourraient traiter la même offre à la même
         * seconde. `expireAndEscalate()` est transactionnel et idempotent, donc rien ne casserait
         * — mais on ferait deux fois le travail de recherche de candidat pour rien, à chaque
         * passage. Une minute suffit : au-delà, le job a manifestement disparu.
         */
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

                /*
                 * UN ÉCHEC N'ARRÊTE PAS LE BALAYAGE. Une mission dont la réescalade tombe ne doit
                 * pas retenir les vingt suivantes : c'est justement le rôle d'un filet que de
                 * traiter ce qui reste.
                 */
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
