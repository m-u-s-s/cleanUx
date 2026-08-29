<?php

namespace Database\Factories;

use App\Models\PeerInspection;
use App\Models\PeerRental;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerInspection> */
class PeerInspectionFactory extends Factory
{
    protected $model = PeerInspection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_rental_id' => PeerRental::factory(),
            'phase' => PeerInspection::PHASE_DEPART,
            'mileage_km' => $this->faker->numberBetween(10000, 150000),
            'fuel_eighths' => 8,
            'cleanliness' => 'propre',
            'license_verified' => true,
        ];
    }

    public function signee(): static
    {
        return $this->state(fn (array $attributs): array => [
            'owner_signed_at' => now(),
            'renter_signed_at' => now(),
        ]);
    }
}
