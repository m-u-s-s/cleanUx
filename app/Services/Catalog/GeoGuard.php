<?php

namespace App\Services\Catalog;

use App\Models\Booking;
use App\Models\Country;
use App\Models\ServiceZone;

/**
 * Les règles géographiques du catalogue, hors de toute interface.
 *
 * POURQUOI UN SERVICE ET NON DES MÉTHODES DE MODÈLE. Trois écrans les liront, et le moteur
 * tarifaire au lot suivant. Surtout : une règle de suppression fausse ne se manifeste qu'au moment
 * où elle détruit quelque chose. La tester à travers un composant, c'est la tester à travers le
 * rendu, la validation et l'autorisation — trois raisons de passer au vert par accident.
 */
class GeoGuard
{
    /**
     * Ce qui empêche de supprimer un pays. Tableau vide = suppression permise.
     *
     * ON REND DES RAISONS ET NON UN BOOLÉEN. « Ça ne se supprime pas » sans dire pourquoi oblige à
     * ouvrir la base pour comprendre, ce à quoi un administrateur n'a pas accès. Le compte fait
     * partie de la raison : « 6 zones rattachées » se vérifie d'un coup d'œil, « des zones
     * existent » ne se vérifie pas.
     *
     * @return list<string>
     */
    public function raisonsDeNePasSupprimerPays(Country $pays): array
    {
        $raisons = [];

        $zones = $pays->serviceZones()->count();

        if ($zones > 0) {
            $raisons[] = "{$zones} zone(s) rattachée(s) à ce pays";
        }

        return $raisons;
    }

    /**
     * Ce qui empêche de supprimer une zone. Tableau vide = suppression permise.
     *
     * @return list<string>
     */
    public function raisonsDeNePasSupprimerZone(ServiceZone $zone): array
    {
        $raisons = [];

        $reservations = Booking::query()->where('service_zone_id', $zone->id)->count();

        if ($reservations > 0) {
            // Supprimer emporterait de l'historique de facturation, qui doit rester consultable
            // bien après la fermeture d'une zone.
            $raisons[] = "{$reservations} réservation(s) rattachée(s) à cette zone";
        }

        return $raisons;
    }

    /**
     * Une zone est-elle atteignable par un client ?
     *
     * C'EST UNE LECTURE ET JAMAIS UNE ÉCRITURE. Éteindre un pays ne doit pas modifier ses zones :
     * sinon la réactivation ne saurait plus lesquelles étaient éteintes pour leur propre raison,
     * et les rallumerait toutes. Le défaut ne se verrait qu'au moment où un client réserve dans
     * une zone qu'on croyait fermée.
     *
     * La règle se lit dans les deux sens : un pays actif ne rachète pas une zone fermée.
     */
    public function zoneEstJoignable(ServiceZone $zone): bool
    {
        if (! $zone->is_bookable || $zone->status !== 'active') {
            return false;
        }

        return (bool) $zone->country?->is_active;
    }
}
