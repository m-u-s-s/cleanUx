<?php

namespace App\Services\PeerRental;

use App\Models\PeerInspection;
use App\Models\PeerRental;

/**
 * CE QUE LE RETOUR AJOUTE A LA NOTE.
 *
 * Kilometres au-dela du forfait, carburant manquant, retard : trois mesures, trois lignes,
 * et le detail de chacune. Un montant global que personne ne peut refaire a la main se
 * conteste sans fin — ici chaque ligne porte sa formule.
 */
class PeerReturnCharges
{
    /**
     * @return array{
     *   total_cents: int,
     *   lignes: list<array{cle: string, libelle: string, detail: string, cents: int}>
     * }
     */
    public function calculer(PeerRental $location): array
    {
        $depart = $location->inspection(PeerInspection::PHASE_DEPART);
        $retour = $location->inspection(PeerInspection::PHASE_RETOUR);

        $lignes = [];

        foreach ([
            $this->kilometrage($location, $depart, $retour),
            $this->carburant($location, $depart, $retour),
            $this->retard($location),
        ] as $ligne) {
            if ($ligne !== null && $ligne['cents'] > 0) {
                $lignes[] = $ligne;
            }
        }

        return [
            'total_cents' => array_sum(array_column($lignes, 'cents')),
            'lignes' => $lignes,
        ];
    }

    /** @return array{cle: string, libelle: string, detail: string, cents: int}|null */
    private function kilometrage(PeerRental $location, ?PeerInspection $depart, ?PeerInspection $retour): ?array
    {
        if ($depart?->mileage_km === null || $retour?->mileage_km === null) {
            return null;
        }

        $parcourus = max(0, $retour->mileage_km - $depart->mileage_km);
        $depassement = max(0, $parcourus - $location->included_km);

        if ($depassement === 0 || $location->extra_km_price_cents <= 0) {
            return null;
        }

        return [
            'cle' => 'kilometrage',
            'libelle' => __('Kilomètres supplémentaires'),
            'detail' => __(':parcourus km parcourus, :inclus inclus — :sup km à :prix', [
                'parcourus' => $parcourus,
                'inclus' => $location->included_km,
                'sup' => $depassement,
                'prix' => number_format($location->extra_km_price_cents / 100, 2, ',', ' ').' €',
            ]),
            'cents' => $depassement * $location->extra_km_price_cents,
        ];
    }

    /** @return array{cle: string, libelle: string, detail: string, cents: int}|null */
    private function carburant(PeerRental $location, ?PeerInspection $depart, ?PeerInspection $retour): ?array
    {
        if ($depart?->fuel_eighths === null || $retour?->fuel_eighths === null) {
            return null;
        }

        $manquants = max(0, $depart->fuel_eighths - $retour->fuel_eighths);

        if ($manquants === 0) {
            return null;
        }

        $parHuitieme = (int) config('peer_rental.fuel_missing_eighth_cents', 1200);

        return [
            'cle' => 'carburant',
            'libelle' => __('Carburant manquant'),
            'detail' => __(':n huitième(s) de réservoir', ['n' => $manquants]),
            'cents' => $manquants * $parHuitieme,
        ];
    }

    /**
     * LE RETARD SE COMPTE EN HEURES ENTAMEES.
     *
     * Une tolerance d'une heure evite de facturer un embouteillage ; au-dela, chaque heure
     * commencee est due, parce que c'est le proprietaire qui attend.
     *
     * @return array{cle: string, libelle: string, detail: string, cents: int}|null
     */
    private function retard(PeerRental $location): ?array
    {
        if ($location->returned_at === null) {
            return null;
        }

        $minutes = $location->ends_at->diffInMinutes($location->returned_at, false);

        if ($minutes <= 60) {
            return null;
        }

        $heures = (int) ceil($minutes / 60);
        $parHeure = (int) config('peer_rental.late_return_fee_per_hour_cents', 1500);

        return [
            'cle' => 'retard',
            'libelle' => __('Retard au retour'),
            'detail' => __(':n heure(s) entamée(s)', ['n' => $heures]),
            'cents' => $heures * $parHeure,
        ];
    }
}
