<?php

namespace Database\Factories;

use App\Models\RentalPickupPoint;
use App\Models\RentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalVehicle> */
class RentalVehicleFactory extends Factory
{
    protected $model = RentalVehicle::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => RentalVehicle::genererUnCode(),
            'plate' => strtoupper(fake()->bothify('#-???-###')),
            'brand' => fake()->randomElement(['Renault', 'Peugeot', 'Volkswagen', 'Toyota', 'BMW']),
            'model' => fake()->randomElement(['Clio', '208', 'Golf', 'Yaris', 'Serie 1']),
            'year' => fake()->numberBetween(2019, 2026),
            'color' => fake()->safeColorName(),
            'category' => fake()->randomElement(['citadine', 'compacte', 'berline', 'suv', 'utilitaire']),
            'transmission' => fake()->randomElement([RentalVehicle::TRANSMISSION_MANUELLE, RentalVehicle::TRANSMISSION_AUTOMATIQUE]),
            'fuel' => fake()->randomElement(['essence', 'diesel', 'hybride', 'electrique']),
            'seats' => 5,
            'doors' => 5,
            'luggage' => 2,
            'features' => ['climatisation', 'bluetooth'],
            'daily_price_cents' => fake()->numberBetween(3500, 12000),
            'currency' => 'EUR',
            'deposit_cents' => 80000,
            'waiver_daily_price_cents' => 1200,
            'waiver_deposit_cents' => 20000,
            'included_km_per_day' => 200,
            'extra_km_price_cents' => 25,
            'min_rental_days' => 1,
            'max_rental_days' => 30,
            'min_driver_age' => 21,
            'min_license_years' => 1,
            'pickup_point_id' => fn () => RentalPickupPoint::factory(),
            // FERMÉE PAR DÉFAUT, comme un pays neuf : une voiture ne se met pas en vitrine
            // par accident. Les tests qui veulent une voiture proposable disent `->actif()`.
            'is_active' => false,
            'sort_order' => 0,
        ];
    }

    public function actif(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    /** Un véhicule qui ne propose aucune garantie : caution pleine, pas de supplément. */
    public function sansGarantie(): static
    {
        return $this->state(fn () => [
            'waiver_daily_price_cents' => 0,
            'waiver_deposit_cents' => 80000,
            'deposit_cents' => 80000,
        ]);
    }
}
