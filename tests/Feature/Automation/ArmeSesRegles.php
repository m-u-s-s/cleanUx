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
}
