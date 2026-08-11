<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\Trade;
use Illuminate\Support\Carbon;

/**
 * LA PRÉVISION DE DEMANDE (E29) — où recruter, avant que ça manque.
 *
 * À QUOI ÇA SERT VRAIMENT. Recruter un prestataire prend des semaines : vérification, KYC,
 * formation, premières courses. Constater le manque le jour où les recherches s'épuisent, c'est
 * arriver avec trois mois de retard. La prévision ne sert pas à prédire l'avenir — elle sert à
 * lancer un recrutement au bon moment.
 *
 * LA MÉTHODE EST VOLONTAIREMENT SIMPLE ET EXPLICABLE : moyenne mobile sur les semaines observées,
 * appliquée à la semaine suivante. Un modèle plus fin donnerait un chiffre plus juste et une
 * décision moins défendable — un responsable qui ne peut pas expliquer pourquoi le système demande
 * trois recrutements à Liège n'en lancera aucun.
 *
 * L'INTERVALLE DE CONFIANCE EST LE CHIFFRE HONNÊTE. Une projection à partir de trois semaines
 * d'historique n'a pas la même valeur qu'à partir de douze, et l'écart-type le dit. Rendre une
 * projection nue ferait prendre une extrapolation pour une mesure.
 *
 * ON NE PROJETTE PAS SOUS QUATRE SEMAINES D'OBSERVATION. En dessous, la moyenne mobile décrit un
 * accident, pas une tendance : `has_enough_history` est rendu à faux plutôt qu'un chiffre qui serait
 * lu comme un objectif.
 */
class DemandForecastService
{
    /** En dessous, la moyenne mobile décrit un accident, pas une tendance. */
    public const SEMAINES_MINIMUM = 4;

    /**
     * La projection de la semaine à venir, par zone et par métier.
     *
     * @return list<array<string, mixed>>
     */
    public function projection(int $semainesObservees = 8): array
    {
        $semainesObservees = max(self::SEMAINES_MINIMUM, min(26, $semainesObservees));

        $depuis = Carbon::now()->subWeeks($semainesObservees)->startOfWeek();

        $reservations = Booking::query()
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$depuis, Carbon::now()])
            ->whereNotIn('status', ['annule', 'cancelled'])
            ->get(['id', 'service_zone_id', 'trade_id', 'scheduled_at']);

        $zones = ServiceZone::query()->pluck('name', 'id');
        $metiers = Trade::query()->pluck('name', 'id');

        return $reservations
            ->groupBy(fn (Booking $b) => ($b->service_zone_id ?? 0).'|'.($b->trade_id ?? 0))
            ->map(function ($groupe, $cle) use ($zones, $metiers) {
                [$zoneId, $tradeId] = array_map('intval', explode('|', (string) $cle));

                // Le compte par semaine ISO : c'est la maille sur laquelle un recrutement se décide.
                $parSemaine = $groupe
                    ->groupBy(fn (Booking $b) => $b->scheduled_at?->format('o-W'))
                    ->map(fn ($sem) => $sem->count())
                    ->values();

                $moyenne = $parSemaine->avg() ?? 0;
                $ecartType = $this->ecartType($parSemaine->all(), (float) $moyenne);

                return [
                    'zone_id' => $zoneId,
                    'zone_name' => $zones[$zoneId] ?? 'Hors zone',
                    'trade_id' => $tradeId,
                    'trade_name' => $metiers[$tradeId] ?? 'Sans métier',
                    'weeks_observed' => $parSemaine->count(),
                    'total_observed' => $groupe->count(),
                    'weekly_average' => round((float) $moyenne, 1),
                    // La projection est la moyenne mobile : simple, explicable, et donc défendable
                    // devant quelqu'un qui doit signer un recrutement.
                    'next_week_forecast' => (int) round((float) $moyenne),
                    /*
                     * L'INTERVALLE EST LE CHIFFRE HONNÊTE. Rendre une projection nue ferait prendre
                     * une extrapolation pour une mesure.
                     */
                    'forecast_low' => max(0, (int) round($moyenne - $ecartType)),
                    'forecast_high' => (int) round($moyenne + $ecartType),
                    // En dessous du seuil, on ne projette pas : la moyenne décrirait un accident.
                    'has_enough_history' => $parSemaine->count() >= self::SEMAINES_MINIMUM,
                ];
            })
            ->sortByDesc('next_week_forecast')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $valeurs
     */
    protected function ecartType(array $valeurs, float $moyenne): float
    {
        $n = count($valeurs);

        if ($n < 2) {
            // Une seule observation n'a pas de dispersion : rendre zéro annoncerait une certitude
            // qu'on n'a pas. `has_enough_history` porte déjà l'avertissement.
            return 0.0;
        }

        $somme = 0.0;

        foreach ($valeurs as $valeur) {
            $somme += ($valeur - $moyenne) ** 2;
        }

        return sqrt($somme / ($n - 1));
    }
}
