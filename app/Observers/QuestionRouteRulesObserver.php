<?php

namespace App\Observers;

use App\Models\Question;
use App\Models\Trade;
use App\Support\Domain\QuestionType;
use App\Support\Domain\TradeRouteRules;

/**
 * DATE LE JOUR OÙ UN MÉTIER EST DEVENU UN TRAJET.
 *
 * `TradeRouteRules::estUnTrajet()` répond à « ce métier est-il un trajet MAINTENANT ». Personne ne
 * sait répondre à « depuis quand », et c'est pourtant cette date qui compte : elle fait courir le
 * délai laissé aux prestataires déjà inscrits pour fournir leur permis. Sans elle, ajouter une
 * question d'arrivée un mardi matin couperait le même jour tous les prestataires du métier, avant
 * même qu'ils aient été prévenus de ce qu'on leur demande.
 *
 * L'OBSERVATEUR EST LE SEUL ÉCRIVAIN, et c'est délibéré. Trois portes mènent aux questions — l'écran
 * web, l'API de la console mobile, l'import d'un parcours — et une date posée dans chacune finirait
 * par manquer dans la quatrième. Ici, elle suit la table qu'elle décrit.
 *
 * LA DATE EST EFFACÉE quand le métier cesse d'être un trajet. Un métier qui redeviendrait un trajet
 * plus tard rouvrirait alors un délai neuf : c'est le comportement généreux, et le seul défendable
 * — on ne coupe pas quelqu'un au titre d'une exigence levée entre-temps.
 */
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
