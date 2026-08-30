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
        $regle->forceFill(['etat' => AutomationRule::ETAT_OBSERVATION])->save();
        app(RuleRunner::class)->executer($regle);
        app(EtatDeRegle::class)->armer($regle->fresh());

        return $regle->fresh();
    }
}
