<?php

namespace App\Services\OrderEngine;

use App\Models\Trade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Les professionnels proposés au client, quand il veut choisir.
 *
 * Le choix est FACULTATIF, et c'est le point. Par défaut, « le meilleur professionnel disponible »
 * est déjà retenu et suffit pour continuer : obliger à trancher entre douze inconnus transforme un
 * service en corvée de comparaison, sur des critères que le client n'a aucun moyen d'arbitrer.
 *
 * Ceux qui veulent choisir trouvent ici de quoi le faire — note, missions accomplies, distance —
 * et rien de décoratif. Un badge « professionnel vérifié » sur tout le monde n'aide personne.
 */
class ProviderShortlist
{
    public function __construct(
        protected ProviderAvailabilityLookup $lookup,
    ) {}

    /**
     * @return Collection<int, array{
     *     id: int, name: string, rating: float|null, rating_count: int,
     *     missions_count: int, distance_m: int, distance_km: float
     * }>
     */
    public function forTrade(Trade $trade, float $lat, float $lng, int $limit = 6): Collection
    {
        $nearby = $this->lookup->nearby($trade, $lat, $lng)->take($limit);

        if ($nearby->isEmpty()) {
            return collect();
        }

        $distances = $nearby->pluck('distance_m', 'id');

        $rows = DB::table('users')
            ->join('provider_profiles', 'provider_profiles.user_id', '=', 'users.id')
            ->whereIn('users.id', $nearby->pluck('id'))
            ->select([
                'users.id',
                'users.name',
                'provider_profiles.rating_avg',
                'provider_profiles.rating_count',
            ])
            ->get();

        $missions = $this->completedMissionsFor($rows->pluck('id')->all());

        return $rows
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                // La note n'est affichée que si elle repose sur quelque chose : une moyenne sur un
                // seul avis dit moins que pas de note du tout, et prétend le contraire.
                'rating' => ((int) $row->rating_count) >= 3 ? round((float) $row->rating_avg, 1) : null,
                'rating_count' => (int) $row->rating_count,
                'missions_count' => (int) ($missions[$row->id] ?? 0),
                'distance_m' => (int) ($distances[$row->id] ?? 0),
                'distance_km' => round(((int) ($distances[$row->id] ?? 0)) / 1000, 1),
            ])
            ->sortBy('distance_m')
            ->values();
    }

    /**
     * Missions réellement terminées, par prestataire.
     *
     * Une requête groupée plutôt qu'une par ligne : la liste en compte six, mais la même erreur
     * répétée sur chaque écran de la plateforme finit par se voir.
     *
     * @param  list<int>  $providerIds
     * @return array<int, int>
     */
    protected function completedMissionsFor(array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }

        return DB::table('missions')
            ->whereIn('lead_provider_user_id', $providerIds)
            ->where('status', 'completed')
            ->groupBy('lead_provider_user_id')
            ->selectRaw('lead_provider_user_id, count(*) as total')
            ->pluck('total', 'lead_provider_user_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
