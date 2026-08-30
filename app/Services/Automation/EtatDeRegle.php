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
        $observees = LigneDeJournal::query()
            ->where('automation_rule_id', $regle->id)
            ->where('mode', 'observation')
            ->where('resultat', LigneDeJournal::RESULTAT_SIMULEE)
            ->count();

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

    /** @param array<string, mixed> $meta */
    protected function poser(AutomationRule $regle, string $etat, string $suffixe, array $meta = []): void
    {
        $regle->forceFill(['etat' => $etat, 'plafonds_consecutifs' => 0])->save();

        ActivityLogger::log('automation.regle_'.$suffixe, $regle, $meta);
    }
}
