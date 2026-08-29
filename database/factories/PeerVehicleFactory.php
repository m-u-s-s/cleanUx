<?php

namespace Database\Factories;

use App\Models\PeerVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerVehicle> */
class PeerVehicleFactory extends Factory
{
    protected $model = PeerVehicle::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => PeerVehicle::genererUneReference(),
            'owner_id' => User::factory(),
            'status' => PeerVehicle::STATUT_BROUILLON,
            'brand' => $this->faker->randomElement(['Renault', 'Peugeot', 'Volkswagen', 'Toyota', 'BMW']),
            'model' => $this->faker->randomElement(['Clio', '208', 'Golf', 'Yaris', 'Serie 1']),
            'year' => $this->faker->numberBetween(2015, 2025),
            'color' => $this->faker->randomElement(['noir', 'blanc', 'gris', 'bleu']),
            'plate' => strtoupper($this->faker->bothify('#-???-###')),
            'category' => $this->faker->randomElement(['citadine', 'berline', 'suv', 'utilitaire']),
            'transmission' => PeerVehicle::TRANSMISSION_MANUELLE,
            'fuel' => $this->faker->randomElement(['essence', 'diesel', 'hybride', 'electrique']),
            'seats' => 5,
            'doors' => 5,
            'luggage' => 2,
            'description' => $this->faker->sentence(12),
            'daily_price_cents' => $this->faker->numberBetween(2500, 12000),
            'currency' => 'EUR',
            'deposit_cents' => 50000,
            'included_km_per_day' => 200,
            'extra_km_price_cents' => 25,
            'min_rental_days' => 1,
            'max_rental_days' => 30,
            'address_line' => $this->faker->streetAddress(),
            'postal_code' => '1000',
            'city' => 'Bruxelles',
            'country_code' => 'BE',
            'lat' => 50.8503,
            'lng' => 4.3517,
            'cancellation_policy' => PeerVehicle::ANNULATION_MODEREE,
        ];
    }

    /** Une annonce visible et reservable : papiers valides, photo de couverture posee. */
    public function publiee(): static
    {
        return $this->state(fn (array $attributs): array => [
            'status' => PeerVehicle::STATUT_PUBLIE,
            'published_at' => now(),
        ]);
    }

    public function reservationInstantanee(): static
    {
        return $this->state(fn (array $attributs): array => ['instant_booking' => true]);
    }
}
