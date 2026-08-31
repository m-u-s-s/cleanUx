<?php

namespace App\Console\Commands;

use App\Models\AutomationRule;
use App\Services\Automation\FileDeReevaluation;
use App\Services\Automation\RuleRunner;
use App\Services\FeatureFlag\FeatureFlagService;
use Illuminate\Console\Command;
use Throwable;

class ExecuterLAutomatisation extends Command
{
    protected $signature = 'automation:executer';

    protected $description = "Exécute les règles d'automatisation dont le tour est venu.";

    private const ETATS_ACTIFS = [
        AutomationRule::ETAT_OBSERVATION,
        AutomationRule::ETAT_ARMEE,
    ];

    public function handle(RuleRunner $runner, FeatureFlagService $drapeaux, FileDeReevaluation $file): int
    {
        if (! $drapeaux->isEnabled('automation')) {
            $this->info("Moteur d'automatisation coupé (drapeau « automation »).");

            return self::SUCCESS;
        }

        $this->drainer($runner, $file);

        $regles = AutomationRule::query()
            ->whereIn('etat', self::ETATS_ACTIFS)
            ->where('declencheur', 'cadence')
            ->get()
            ->filter(fn (AutomationRule $regle): bool => $this->estDue($regle));

        foreach ($regles as $regle) {
            try {
                $passage = $runner->executer($regle);
            } catch (Throwable $e) {
                // Meme chemin que le drain : compte comme un echec, peut suspendre au bout de trois.
                $runner->enregistrerEchec($regle, mb_substr($e->getMessage(), 0, 250));
                $this->error(sprintf('%s : %s', $regle->nom, $e->getMessage()));

                continue;
            }

            $this->line(sprintf(
                '%s : %d entité(s), %d action(s), %s',
                $regle->nom,
                $passage->entites_vues,
                $passage->actions_posees,
                $passage->statut
            ));
        }

        return self::SUCCESS;
    }

    /** Le drain passe AVANT la cadence : une alerte levee se traite au premier passage. */
    protected function drainer(RuleRunner $runner, FileDeReevaluation $file): void
    {
        foreach ($file->parEvenement() as $groupe) {
            $regles = AutomationRule::query()
                ->whereIn('etat', self::ETATS_ACTIFS)
                ->where('declencheur', $groupe['evenement'])
                ->where('entite', $groupe['entite'])
                ->get();

            // Aucune regle branchee : rien a attendre, la file grossirait sans fin sinon.
            if ($regles->isEmpty()) {
                $file->purger($groupe['lignes']);

                continue;
            }

            $ensembles = [];

            foreach ($regles as $regle) {
                try {
                    $passage = $runner->executer($regle, $groupe['identifiants']);
                } catch (Throwable $e) {
                    // Compte comme un echec total : suspend au bout de trois, ne fige plus le groupe.
                    $runner->enregistrerEchec($regle, mb_substr($e->getMessage(), 0, 250));
                    $this->error(sprintf('%s (%s) : %s', $regle->nom, $groupe['evenement'], $e->getMessage()));

                    continue;
                }

                $this->line(sprintf(
                    '%s (%s) : %d entité(s), %d action(s), %s',
                    $regle->nom,
                    $groupe['evenement'],
                    $passage->entites_vues,
                    $passage->actions_posees,
                    $passage->statut
                ));

                // Un echec (refus en amont ou levee) est deja exclu de l'intersection.
                if ($passage->statut === 'echec') {
                    continue;
                }

                $ensembles[] = $passage->entites_finies ?? [];
            }

            // Aucun passage ne compte (toutes en echec) : rien n'est confirme traite.
            if ($ensembles === []) {
                continue;
            }

            $finies = array_shift($ensembles);
            foreach ($ensembles as $ensemble) {
                $finies = array_intersect($finies, $ensemble);
            }

            // Une ligne purge quand son entite figure dans TOUTES les regles du groupe :
            // deux regles aux conditions differentes peuvent servir des entites differentes.
            $aPurger = [];
            foreach ($groupe['identifiants'] as $i => $entiteId) {
                if (in_array($entiteId, $finies, true)) {
                    $aPurger[] = $groupe['lignes'][$i];
                }
            }

            $file->purger($aPurger);
        }
    }

    protected function estDue(AutomationRule $regle): bool
    {
        if ($regle->dernier_passage_le === null) {
            return true;
        }

        $minutes = AutomationRule::CADENCES[$regle->cadence] ?? 15;

        return $regle->dernier_passage_le->addMinutes($minutes)->isPast();
    }
}
