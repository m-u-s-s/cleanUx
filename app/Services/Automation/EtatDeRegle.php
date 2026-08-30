<?php

namespace App\Services\Automation;

use App\Models\AutomationAction as LigneDeJournal;
use App\Models\AutomationRule;
use App\Support\ActivityLogger;

/** Les transitions d'une regle, et la seule qui refuse. */
class EtatDeRegle
{
    public function observer(AutomationRule $regle): void
    {
        $this->poser($regle, AutomationRule::ETAT_OBSERVATION, 'observation');
    }

    /** @throws ArmementRefuse */
    public function armer(AutomationRule $regle): void
    {
        $observees = $this->observationsSimulees($regle);

        if ($observees === 0) {
            throw new ArmementRefuse(
                "Cette règle n'a rien observé : il n'y a rien à lire avant de l'armer."
            );
        }

        $this->poser($regle, AutomationRule::ETAT_ARMEE, 'armee', ['observees' => $observees]);
    }

    public function suspendre(AutomationRule $regle, string $motif): void
    {
        $this->poser($regle, AutomationRule::ETAT_SUSPENDUE, 'suspendue', ['motif' => $motif]);
    }

    public function desactiver(AutomationRule $regle): void
    {
        $this->poser($regle, AutomationRule::ETAT_DESACTIVEE, 'desactivee');
    }

    /** Le seul endroit qui sait ce que « avoir observe » veut dire — RuleRunner l'appelle aussi. */
    public function aDejaObserve(AutomationRule $regle): bool
    {
        return $this->observationsSimulees($regle) > 0;
    }

    protected function observationsSimulees(AutomationRule $regle): int
    {
        return LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('mode', 'observation')
            ->where('resultat', LigneDeJournal::RESULTAT_SIMULEE)
            ->count();
    }

    /** @param array<string, mixed> $meta */
    protected function poser(AutomationRule $regle, string $etat, string $suffixe, array $meta = []): void
    {
        // Meme remise a zero pour les deux compteurs : sans elle, une regle rearmee
        // se re-suspend au premier passage, sur un compteur jamais purge.
        $regle->forceFill([
            'etat' => $etat,
            'plafonds_consecutifs' => 0,
            'echecs_consecutifs' => 0,
        ])->save();

        ActivityLogger::log('automation.regle_'.$suffixe, $regle, $meta);
    }
}
