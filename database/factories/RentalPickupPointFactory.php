<?php

namespace Database\Factories;

use App\Models\RentalPickupPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalPickupPoint> */
class RentalPickupPointFactory extends Factory
{
    protected $model = RentalPickupPoint::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Agence '.fake()->city(),
            'address' => fake()->streetAddress(),
            'postal_code' => (string) fake()->numberBetween(1000, 9999),
            'city' => fake()->city(),
            'country_code' => 'BE',
            'lat' => fake()->latitude(50, 51),
            'lng' => fake()->longitude(3, 6),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
