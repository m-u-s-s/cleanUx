<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationRule;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\RuleRunner;

/** Arme une regle par le chemin reel : elle observe, puis on l'arme — jamais par fillable. */
trait ArmeSesRegles
{
    private function armer(AutomationRule $regle): AutomationRule
    {
        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        app(EtatDeRegle::class)->armer($regle->fresh());

        return $regle->fresh();
    }

    /**
     * Variante evenementielle : observe restreinte aux identifiants (comme le fait le
     * drain), pas un passage a vide — le seul chemin reel pour une regle sans condition.
     *
     * @param  list<int>  $identifiants
     */
    private function armerParDrain(AutomationRule $regle, array $identifiants): AutomationRule
    {
        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh(), $identifiants);
        app(EtatDeRegle::class)->armer($regle->fresh());

        return $regle->fresh();
    }
}
