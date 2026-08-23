<?php

namespace App\Services\Rental;

use App\Models\RentalBooking;
use App\Models\RentalVehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** CONCLURE UNE LOCATION. */
class RentalBookingService
{
    public function __construct(
        private readonly RentalAvailability $disponibilite,
        private readonly RentalPricing $tarification,
    ) {}

    /**
     * Enregistre la demande sans l'engager : c'est le panier.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function preparer(RentalVehicle $vehicule, array $donnees, ?string $jetonDeSession = null): RentalBooking
    {
        [$debut, $fin] = $this->periode($donnees);

        $prix = $this->tarification->pour($vehicule, $debut, $fin, $this->protection($donnees, $vehicule));
        $agence = $vehicule->pickupPoint;

        return RentalBooking::query()->create([
            'reference' => RentalBooking::genererUneReference(),
            'rental_vehicle_id' => $vehicule->id,
            'session_token' => $jetonDeSession,
            'starts_at' => $debut,
            'ends_at' => $fin,
            'days' => $prix['days'],
            'driver_first_name' => $donnees['driver_first_name'] ?? null,
            'driver_last_name' => $donnees['driver_last_name'] ?? null,
            'driver_birthdate' => $donnees['driver_birthdate'] ?? null,
            'driver_email' => $donnees['driver_email'] ?? null,
            'driver_phone' => $donnees['driver_phone'] ?? null,
            'license_number' => $donnees['license_number'] ?? null,
            'license_country' => $donnees['license_country'] ?? null,
            'license_issued_at' => $donnees['license_issued_at'] ?? null,
            'protection' => $this->protection($donnees, $vehicule),
            'daily_price_cents' => $vehicule->daily_price_cents,
            'subtotal_cents' => $prix['subtotal_cents'],
            'waiver_total_cents' => $prix['waiver_total_cents'],
            'total_cents' => $prix['total_cents'],
            'deposit_cents' => $prix['deposit_cents'],
            'currency' => $prix['currency'],
            // L'adresse promise est COPIÉE : l'agence peut déménager, la promesse ne bouge pas.
            'pickup_label' => $agence?->name,
            'pickup_address' => $agence?->adresseComplete(),
            'pickup_lat' => $agence?->lat,
            'pickup_lng' => $agence?->lng,
            'status' => RentalBooking::STATUT_BROUILLON,
        ]);
    }

    /**
     * Engage la location — c'est ici que la voiture cesse d'être disponible.
     *
     * @throws ValidationException
     */
    public function confirmer(RentalBooking $location, ?int $clientId = null): RentalBooking
    {
        return DB::transaction(function () use ($location, $clientId) {
            $location->refresh()->loadMissing('vehicle');
            $vehicule = $location->vehicle;

            $this->exigerUnPermisRecevable($location, $vehicule);
            $this->exigerUneDureeRecevable($location, $vehicule);

            // LA DISPONIBILITÉ SE REVÉRIFIE ICI, DANS LA TRANSACTION.
            if (! $this->disponibilite->estLibre($vehicule, $location->starts_at, $location->ends_at, $location->id)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Ce véhicule vient d’être réservé sur ces dates. Choisissez d’autres dates ou une autre voiture.',
                ]);
            }

            $location->forceFill([
                'client_id' => $clientId ?? $location->client_id,
                'status' => RentalBooking::STATUT_CONFIRMEE,
                'confirmed_at' => now(),
            ])->save();

            return $location->refresh();
        });
    }

    public function annuler(RentalBooking $location, ?string $motif = null): RentalBooking
    {
        if (! $location->estAnnulable()) {
            throw ValidationException::withMessages([
                'status' => 'Cette location ne peut plus être annulée.',
            ]);
        }

        $location->forceFill([
            'status' => RentalBooking::STATUT_ANNULEE,
            'cancelled_at' => now(),
            'metadata' => array_merge((array) $location->metadata, array_filter(['cancel_reason' => $motif])),
        ])->save();

        return $location->refresh();
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * LE PERMIS ET L'ÂGE, JUGÉS AU JOUR DU DÉPART.
     *
     * @throws ValidationException
     */
    private function exigerUnPermisRecevable(RentalBooking $location, RentalVehicle $vehicule): void
    {
        $depart = $location->starts_at ?? now();

        if ($location->driver_birthdate !== null) {
            $age = $location->driver_birthdate->diffInYears($depart);

            if ($age < $vehicule->min_driver_age) {
                throw ValidationException::withMessages([
                    'driver_birthdate' => "Ce véhicule est réservé aux conducteurs de {$vehicule->min_driver_age} ans et plus.",
                ]);
            }
        }

        if ($location->license_issued_at !== null) {
            $anciennete = $location->license_issued_at->diffInYears($depart);

            if ($anciennete < $vehicule->min_license_years) {
                $annees = $vehicule->min_license_years;

                throw ValidationException::withMessages([
                    'license_issued_at' => "Ce véhicule demande un permis de {$annees} an(s) minimum au départ.",
                ]);
            }
        }
    }

    /** @throws ValidationException */
    private function exigerUneDureeRecevable(RentalBooking $location, RentalVehicle $vehicule): void
    {
        if ($vehicule->max_rental_days !== null && $location->days > $vehicule->max_rental_days) {
            throw ValidationException::withMessages([
                'ends_at' => "Ce véhicule se loue au maximum {$vehicule->max_rental_days} jours.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function periode(array $donnees): array
    {
        $debut = isset($donnees['starts_at']) ? Carbon::parse((string) $donnees['starts_at']) : Carbon::now();
        $fin = isset($donnees['ends_at']) ? Carbon::parse((string) $donnees['ends_at']) : $debut->copy()->addDay();

        return [$debut, $fin];
    }

    /**
     * La protection retenue, ramenée à ce que le véhicule propose réellement.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function protection(array $donnees, RentalVehicle $vehicule): string
    {
        $demandee = (string) ($donnees['protection'] ?? RentalVehicle::PROTECTION_SANS);

        return $demandee === RentalVehicle::PROTECTION_AVEC && $vehicule->proposeUneGarantie()
            ? RentalVehicle::PROTECTION_AVEC
            : RentalVehicle::PROTECTION_SANS;
    }
}
