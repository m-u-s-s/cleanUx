<?php

namespace App\Support\Domain;

use App\Models\Question;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Builder;

/** « CE MÉTIER EST-IL UN TRAJET ? » — une seule réponse, dérivée du parcours lui-même. */
final class TradeRouteRules
{
    /** Le parcours de ce métier décrit-il un trajet d'un point à un autre ? */
    public static function estUnTrajet(Trade $trade): bool
    {
        return self::questionDepart($trade) !== null && self::questionArrivee($trade) !== null;
    }

    public static function questionDepart(Trade $trade): ?Question
    {
        return self::questionDeRole($trade, LocationRole::PICKUP);
    }

    public static function questionArrivee(Trade $trade): ?Question
    {
        return self::questionDeRole($trade, LocationRole::DROPOFF);
    }

    /**
     * Les métiers de trajet, en une requête.
     *
     * @param  Builder<Trade>  $query
     */
    public static function scopeTrajet(Builder $query): void
    {
        foreach (LocationRole::all() as $role) {
            $query->whereExists(function ($sous) use ($role) {
                $sous->selectRaw('1')
                    ->from('questions')
                    ->whereColumn('questions.trade_id', 'trades.id')
                    ->where('questions.type', QuestionType::LOCATION)
                    ->where('questions.location_role', $role)
                    ->where('questions.is_active', true)
                    ->whereNull('questions.deleted_at');
            });
        }
    }

    private static function questionDeRole(Trade $trade, string $role): ?Question
    {
        // La relation déjà chargée est réutilisée quand elle existe : le constructeur de parcours et l'écran de commande ont l'un et l'autre la collection en main, et repartir en base pour chaque question ferait une requête par ligne de catalogue affichée.
        if ($trade->relationLoaded('questions')) {
            return $trade->questions
                ->first(fn (Question $question) => $question->type === QuestionType::LOCATION
                    && $question->location_role === $role
                    && (bool) $question->is_active);
        }

        return $trade->questions()
            ->where('type', QuestionType::LOCATION)
            ->where('location_role', $role)
            ->where('is_active', true)
            ->first();
    }
}
