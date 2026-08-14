<?php

namespace App\Observers;

use App\Models\Trade;

/**
 * DATE LE JOUR OÙ LES RÈGLES TAXI SONT NÉES SUR UN MÉTIER.
 *
 * `trades.taxi_rules` dit qu'un métier exige aujourd'hui un véhicule récent. Il ne dit pas DEPUIS
 * QUAND, et c'est cette date-là qui décide : elle fait courir le délai laissé aux prestataires déjà
 * inscrits pour fournir carte grise et assurance. Sans elle, cocher la case un mardi matin couperait
 * le même jour tous les prestataires du métier — avant même qu'ils sachent ce qu'on leur demande.
 *
 * L'OBSERVATEUR EST LE SEUL ÉCRIVAIN. Trois portes écrivent `taxi_rules` : le formulaire du
 * catalogue web, la console d'administration mobile, et les seeders. Une date posée dans chacune
 * finirait par manquer dans la quatrième, et un métier passerait alors en exigence immédiate sans
 * que personne ne l'ait décidé.
 *
 * ELLE NE BOUGE QU'À LA BASCULE. La reposer à chaque enregistrement relancerait le délai à l'infini :
 * un administrateur qui corrige une faute de frappe dans la description repousserait l'échéance de
 * tout le monde sans le savoir.
 *
 * ÉTEINDRE LA RÈGLE EFFACE LA DATE : la rallumer plus tard rouvre un délai neuf, parce qu'on ne
 * coupe pas quelqu'un au titre d'une exigence levée entre-temps.
 */
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
