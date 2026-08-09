<?php

namespace App\Services\Catalog;

use App\Models\EmployeeZoneAssignment;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CE QU'UN PRESTATAIRE DÉCLARE FAIRE, ET OÙ — écrit une seule fois.
 *
 * Trois chemins écrivaient cette déclaration : l'inscription web, l'inscription mobile et l'écran de
 * profil. Chacun avec ses colonnes, aucun avec les zones. Le `CandidateFinder` lit exactement ces
 * deux tables — `trade_user` et `employee_zone_assignments` — donc une écriture manquante quelque
 * part se traduit par un prestataire qui ne reçoit jamais rien, sans erreur nulle part.
 *
 * ON N'ÉCRIT QUE CE QUE LE CATALOGUE AUTORISE. Les identifiants viennent d'un formulaire, donc du
 * navigateur : accepter un métier fermé dans une zone donnerait une couverture qui ne peut produire
 * aucune mission, et le prestataire attendrait des offres qui ne viendraient jamais.
 *
 * LE COMPTE N'EST JAMAIS CASSÉ. Retirer un métier ou une zone n'invalide rien d'autre : le
 * prestataire garde ses missions passées, ses avis et son historique — il cesse simplement d'être
 * candidat pour ce couple.
 */
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
            /*
             * UNE LISTE VIDE NE VIDE RIEN.
             *
             * `sync([])` effacerait la déclaration existante — et ce chemin est appelé par
             * l'inscription, où les métiers arrivent parfois par un autre champ. Effacer en
             * silence ce que l'utilisateur vient de déclarer est le pire des comportements : il n'a
             * aucune erreur, et découvre des semaines plus tard qu'il ne reçoit rien.
             *
             * Retirer réellement un métier passe donc par une liste NON vide qui ne le contient
             * plus — ce que fait l'écran de profil, où décocher laisse toujours au moins une case.
             */
            if ($metiers === []) {
                $this->syncZones($provider, $zones);

                return;
            }

            /*
             * `sync` et non `syncWithoutDetaching` : retirer un métier de son profil doit
             * réellement le retirer du dispatch. Sans cela, un prestataire qui décoche « toiture »
             * continuerait d'en recevoir, et cesserait de croire son propre écran.
             */
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
     * `employee_zone_assignments` porte des priorités et des fenêtres de validité qu'un
     * administrateur a pu régler. Les effacer pour les réécrire perdrait ce travail à chaque fois
     * que le prestataire coche une case de plus.
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

        /*
         * LA ZONE PRINCIPALE suit la première déclarée. `users.primary_service_zone_id` est lu par
         * la requête candidate du planifié : le laisser vide rendrait le prestataire invisible aux
         * rendez-vous alors qu'il vient de déclarer sa couverture.
         */
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

        /*
         * ON N'EXIGE PAS QUE LE MÉTIER SOIT DÉJÀ VENDU QUELQUE PART.
         *
         * La liste PROPOSÉE à l'inscription, elle, s'y limite (`RegistrationOptionsService`) : on
         * ne met pas en avant un métier qu'aucune zone ne peut servir. Mais REFUSER une déclaration
         * pour cette raison effacerait le métier d'un prestataire dès qu'un administrateur ferme
         * temporairement une zone — et il cesserait de recevoir des missions sans que rien ne le
         * lui dise. Un métier non vendu ne produit simplement aucune offre, ce qui est déjà le cas.
         */
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
