<?php

namespace App\Observers;

use App\Models\Question;
use App\Models\Trade;
use App\Support\Domain\QuestionType;
use App\Support\Domain\TradeRouteRules;

/** DATE LE JOUR OÙ UN MÉTIER EST DEVENU UN TRAJET. */
class QuestionRouteRulesObserver
{
    public function saved(Question $question): void
    {
        $this->synchroniser($question);
    }

    public function deleted(Question $question): void
    {
        $this->synchroniser($question);
    }

    public function restored(Question $question): void
    {
        $this->synchroniser($question);
    }

    private function synchroniser(Question $question): void
    {
        // Seule une localisation peut faire basculer un métier ; recalculer sur chaque frappe d'un
        // libellé de compteur ferait une requête de plus par sauvegarde, pour rien.
        if ($question->type !== QuestionType::LOCATION || $question->trade_id === null) {
            return;
        }

        $trade = Trade::find($question->trade_id);

        if (! $trade) {
            return;
        }

        $trade->unsetRelation('questions');
        $estUnTrajet = TradeRouteRules::estUnTrajet($trade);
        $datee = $trade->route_rules_since !== null;

        if ($estUnTrajet === $datee) {
            return;
        }

        // `forceFill` plutôt qu'`update` : cette colonne n'est pas remplie par un formulaire, et la
        // faire dépendre de la composition de `$fillable` l'exposerait à disparaître en silence.
        $trade->forceFill(['route_rules_since' => $estUnTrajet ? now() : null])->save();
    }
}
