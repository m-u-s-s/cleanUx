<?php

namespace Database\Factories;

use App\Models\PricingZoneState;
use App\Models\ServiceZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingZoneStateFactory extends Factory
{
    protected $model = PricingZoneState::class;

    public function definition(): array
    {
        return [
            'service_zone_id' => fn () => ServiceZone::factory()->create()->id,
            'multiplier' => 1.00,
            'demand_factor' => 1.00,
            'supply_factor' => 1.00,
            'temporal_factor' => 1.00,
            'open_bookings_count' => fake()->numberBetween(0, 50),
            'online_providers_count' => fake()->numberBetween(0, 20),
            'expires_at' => null,
            'metadata' => null,
        ];
    }

    public function surge(): static
    {
        return $this->state(fn () => [
            'multiplier' => fake()->randomFloat(1, 1.2, 2.5),
            'expires_at' => now()->addHour(),
        ]);
    }
}
