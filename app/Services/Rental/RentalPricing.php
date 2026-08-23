<?php

namespace App\Services\Rental;

use App\Models\RentalVehicle;
use App\Support\International\Devise;
use Carbon\CarbonInterface;

/** CE QUE COÛTE UNE LOCATION — AVEC ET SANS GARANTIE, TOUJOURS LES DEUX. */
class RentalPricing
{
    /**
     * Le devis complet, dans les deux hypothèses.
     *
     * @return array{
     * days: int,
     * currency: string,
     * daily_price_cents: int,
     * sans_garantie: array{total_cents: int, deposit_cents: int},
     * avec_garantie: array{total_cents: int, deposit_cents: int, supplement_cents: int},
     * propose_une_garantie: bool
     * }
     */
    public function devis(RentalVehicle $vehicule, ?CarbonInterface $debut, ?CarbonInterface $fin): array
    {
        $jours = $this->joursFactures($vehicule, $debut, $fin);

        $sansGarantie = $vehicule->totalSansGarantie($jours);
        $avecGarantie = $vehicule->totalAvecGarantie($jours);

        return [
            'days' => $jours,
            // La devise du véhicule, jamais une constante : une agence marocaine facture en dirhams.
            'currency' => Devise::premiereRenseignee($vehicule->currency),
            'daily_price_cents' => $vehicule->daily_price_cents,
            'sans_garantie' => [
                'total_cents' => $sansGarantie,
                'deposit_cents' => $vehicule->cautionPour(RentalVehicle::PROTECTION_SANS),
            ],
            'avec_garantie' => [
                'total_cents' => $avecGarantie,
                'deposit_cents' => $vehicule->cautionPour(RentalVehicle::PROTECTION_AVEC),
                'supplement_cents' => max(0, $avecGarantie - $sansGarantie),
            ],
            'propose_une_garantie' => $vehicule->proposeUneGarantie(),
        ];
    }

    /** LE NOMBRE DE JOURS FACTURÉS. */
    public function joursFactures(RentalVehicle $vehicule, ?CarbonInterface $debut, ?CarbonInterface $fin): int
    {
        $minimum = max(1, (int) $vehicule->min_rental_days);

        if ($debut === null || $fin === null || $fin->lessThanOrEqualTo($debut)) {
            return $minimum;
        }

        $heures = $debut->diffInMinutes($fin) / 60;

        return max($minimum, (int) ceil($heures / 24));
    }

    /**
     * Le total à retenir pour la protection choisie.
     *
     * @return array{days: int, subtotal_cents: int, waiver_total_cents: int, total_cents: int, deposit_cents: int, currency: string}
     */
    public function pour(RentalVehicle $vehicule, ?CarbonInterface $debut, ?CarbonInterface $fin, string $protection): array
    {
        $devis = $this->devis($vehicule, $debut, $fin);
        $avecGarantie = $protection === RentalVehicle::PROTECTION_AVEC;

        $sousTotal = $devis['sans_garantie']['total_cents'];
        $garantie = $avecGarantie ? $devis['avec_garantie']['supplement_cents'] : 0;

        return [
            'days' => $devis['days'],
            'subtotal_cents' => $sousTotal,
            'waiver_total_cents' => $garantie,
            'total_cents' => $sousTotal + $garantie,
            'deposit_cents' => $vehicule->cautionPour($protection),
            'currency' => $devis['currency'],
        ];
    }
}
