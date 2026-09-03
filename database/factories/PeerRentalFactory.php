<?php

namespace Database\Factories;

use App\Models\PeerRental;
use App\Models\PeerStay;
use App\Models\PeerVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerRental> */
class PeerRentalFactory extends Factory
{
    protected $model = PeerRental::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $debut = now()->addDays(3)->setTime(10, 0);
        $fin = (clone $debut)->addDays(3);
        $prixJour = 5000;
        $jours = 3;
        $sousTotal = $prixJour * $jours;

        return [
            'reference' => PeerRental::genererUneReference(),
            'peer_vehicle_id' => PeerVehicle::factory(),
            'owner_id' => User::factory(),
            'renter_id' => User::factory(),
            'status' => PeerRental::STATUT_EN_ATTENTE,
            'starts_at' => $debut,
            'ends_at' => $fin,
            'days' => $jours,
            'daily_price_cents' => $prixJour,
            'subtotal_cents' => $sousTotal,
            'total_cents' => $sousTotal,
            'currency' => 'EUR',
            'deposit_cents' => 50000,
            'included_km' => 600,
            'extra_km_price_cents' => 25,
            'payment_status' => PeerRental::PAIEMENT_EN_ATTENTE,
        ];
    }

    /**
     * UNE LOCATION DE LOGEMENT.
     *
     * Le meme contrat, sans vehicule : `peer_vehicle_id` reste nul, et seules les colonnes
     * polymorphes designent le bien.
     */
    public function pourUnLogement(?PeerStay $logement = null): static
    {
        return $this->state(function () use ($logement): array {
            $logement ??= PeerStay::factory()->publiee()->create();

            return [
                'peer_vehicle_id' => null,
                'rentable_type' => PeerStay::class,
                'rentable_id' => $logement->id,
                'owner_id' => $logement->owner_id,
                'included_km' => 0,
                'extra_km_price_cents' => 0,
            ];
        });
    }

    public function confirmee(): static
    {
        return $this->state(fn (array $attributs): array => [
            'status' => PeerRental::STATUT_CONFIRMEE,
            'accepted_at' => now(),
            'payment_status' => PeerRental::PAIEMENT_AUTORISE,
            'stripe_payment_intent_id' => 'pi_'.$this->faker->lexify('??????????'),
            'payment_authorized_at' => now(),
            'payment_authorized_until' => now()->addDays(7),
        ]);
    }

    public function enCours(): static
    {
        return $this->confirmee()->state(fn (array $attributs): array => [
            'status' => PeerRental::STATUT_EN_COURS,
            'handover_owner_confirmed_at' => now(),
            'handover_renter_confirmed_at' => now(),
            'handed_over_at' => now(),
            'payment_status' => PeerRental::PAIEMENT_CAPTURE,
            'payment_captured_at' => now(),
        ]);
    }
}
