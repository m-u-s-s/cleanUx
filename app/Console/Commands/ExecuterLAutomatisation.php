<?php

namespace App\Console\Commands;

use App\Models\AutomationRule;
use App\Services\Automation\FileDeReevaluation;
use App\Services\Automation\RuleRunner;
use App\Services\FeatureFlag\FeatureFlagService;
use Illuminate\Console\Command;

class ExecuterLAutomatisation extends Command
{
    protected $signature = 'automation:executer';

    protected $description = "Exécute les règles d'automatisation dont le tour est venu.";

    private const ETATS_ACTIFS = [
        AutomationRule::ETAT_OBSERVATION,
        AutomationRule::ETAT_ARMEE,
    ];

    private const CADENCES = [
        'chaque_minute' => 1,
        'quart_heure' => 15,
        'heure' => 60,
        'jour' => 1440,
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
            $passage = $runner->executer($regle);

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
        foreach ($file->parEvenement() as $evenement => $groupe) {
            $regles = AutomationRule::query()
                ->whereIn('etat', self::ETATS_ACTIFS)
                ->where('declencheur', $evenement)
                ->get();

            foreach ($regles as $regle) {
                $passage = $runner->executer($regle, $groupe['identifiants']);

                $this->line(sprintf(
                    '%s (%s) : %d entité(s), %d action(s), %s',
                    $regle->nom,
                    $evenement,
                    $passage->entites_vues,
                    $passage->actions_posees,
                    $passage->statut
                ));
            }

            // On purge MEME sans regle branchee : sinon la file grossit sans fin et le
            // meme evenement se relit chaque minute pour rien.
            $file->purger($groupe['lignes']);
        }
    }

    protected function estDue(AutomationRule $regle): bool
    {
        if ($regle->dernier_passage_le === null) {
            return true;
        }

        $minutes = self::CADENCES[$regle->cadence] ?? 15;

        return $regle->dernier_passage_le->addMinutes($minutes)->isPast();
    }
}
