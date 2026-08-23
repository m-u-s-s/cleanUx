<?php

namespace App\Observers;

use App\Models\Trade;

/** DATE LE JOUR OÙ LES RÈGLES TAXI SONT NÉES SUR UN MÉTIER. */
class TradeTaxiRulesObserver
{
    public function saving(Trade $trade): void
    {
        // `isDirty` plutôt que `wasChanged` : on écrit la date DANS la même sauvegarde, sans second
        // aller-retour en base — et sans déclencher une nouvelle passe d'observateurs.
        if (! $trade->isDirty('taxi_rules')) {
            return;
        }

        if ((bool) $trade->taxi_rules) {
            $trade->taxi_rules_since ??= now();

            return;
        }

        $trade->taxi_rules_since = null;
    }
}
