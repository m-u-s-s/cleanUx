<?php

namespace App\Services\Catalog;

use App\Models\Booking;
use App\Models\Country;
use App\Models\ServiceZone;

/** Les règles géographiques du catalogue, hors de toute interface. */
class GeoGuard
{
    /**
     * Ce qui empêche de supprimer un pays. Tableau vide = suppression permise.
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

    /** Une zone est-elle atteignable par un client ? C'EST UNE LECTURE ET JAMAIS UNE ÉCRITURE. */
    public function zoneEstJoignable(ServiceZone $zone): bool
    {
        if (! $zone->is_bookable || $zone->status !== 'active') {
            return false;
        }

        return (bool) $zone->country?->is_active;
    }
}
