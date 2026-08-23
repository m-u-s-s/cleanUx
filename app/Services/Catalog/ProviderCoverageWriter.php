<?php

namespace App\Services\Catalog;

use App\Models\EmployeeZoneAssignment;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** CE QU'UN PRESTATAIRE DÉCLARE FAIRE, ET OÙ — écrit une seule fois. */
class ProviderCoverageWriter
{
    /**
     * @param  list<int>  $tradeIds
     * @param  list<int>  $zoneIds
     * @return array{trades: list<int>, zones: list<int>}
     */
    public function sync(User $provider, array $tradeIds, array $zoneIds, ?int $primaryTradeId = null): array
    {
        $metiers = $this->metiersValides($tradeIds);
        $zones = $this->zonesValides($zoneIds);

        DB::transaction(function () use ($provider, $metiers, $zones, $primaryTradeId) {
            // UNE LISTE VIDE NE VIDE RIEN.
            if ($metiers === []) {
                $this->syncZones($provider, $zones);

                return;
            }

            // `sync` et non `syncWithoutDetaching` : retirer un métier de son profil doit réellement le retirer du dispatch.
            $pivot = [];

            foreach ($metiers as $metierId) {
                $pivot[$metierId] = [
                    // Le premier métier retenu devient le principal, à défaut de choix explicite :
                    // `trade_user.is_primary` sert au score de spécialité, et une liste sans
                    // principal ferait tomber tout le monde à égalité.
                    'is_primary' => $primaryTradeId !== null
                        ? $metierId === $primaryTradeId
                        : $metierId === $metiers[0],
                ];
            }

            $provider->trades()->sync($pivot);

            $this->syncZones($provider, $zones);
        });

        return ['trades' => $metiers, 'zones' => $zones];
    }

    /**
     * Les zones déclarées, en gardant les lignes existantes plutôt qu'en les recréant.
     *
     * @param  list<int>  $zoneIds
     */
    protected function syncZones(User $provider, array $zoneIds): void
    {
        $existantes = EmployeeZoneAssignment::query()
            ->where('user_id', $provider->id)
            ->get()
            ->keyBy('service_zone_id');

        foreach ($zoneIds as $zoneId) {
            $ligne = $existantes->get($zoneId);

            if ($ligne) {
                $ligne->update(['is_active' => true]);

                continue;
            }

            EmployeeZoneAssignment::create([
                'user_id' => $provider->id,
                'service_zone_id' => $zoneId,
                'assignment_type' => 'primary',
                'is_active' => true,
                'is_primary' => false,
                'status' => 'active',
                'coverage_priority' => 100,
            ]);
        }

        // Les zones retirées sont DÉSACTIVÉES, pas supprimées : leur historique explique pourquoi
        // une mission de l'an dernier est partie là-bas.
        EmployeeZoneAssignment::query()
            ->where('user_id', $provider->id)
            ->whereNotIn('service_zone_id', $zoneIds ?: [0])
            ->update(['is_active' => false]);

        // LA ZONE PRINCIPALE suit la première déclarée.
        $principale = $zoneIds[0] ?? null;

        if ($principale !== null) {
            // `forceFill` : la colonne n'est pas assignable en masse sur `User`, et un `update()`
            // l'aurait ignorée EN SILENCE — le prestataire serait resté invisible aux rendez-vous
            // après avoir déclaré sa couverture.
            $provider->forceFill(['primary_service_zone_id' => $principale])->save();
        }
    }

    /**
     * @param  list<int>  $tradeIds
     * @return list<int>
     */
    protected function metiersValides(array $tradeIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $tradeIds)));

        if ($ids === []) {
            return [];
        }

        // ON N'EXIGE PAS QUE LE MÉTIER SOIT DÉJÀ VENDU QUELQUE PART.
        return Trade::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $zoneIds
     * @return list<int>
     */
    protected function zonesValides(array $zoneIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $zoneIds)));

        if ($ids === []) {
            return [];
        }

        return ServiceZone::query()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
