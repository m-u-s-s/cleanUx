<?php

namespace Database\Factories;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeZonePricingFactory extends Factory
{
    protected $model = TradeZonePricing::class;

    public function definition(): array
    {
        // Realistic base rates: 1500–8000 cents (15€–80€/h)
        $baseRate = fake()->numberBetween(1500, 8000);

        return [
            'trade_id'         => Trade::factory(),
            'service_zone_id'  => ServiceZone::factory(),
            'base_rate_cents'  => $baseRate,
            'surge_multiplier' => fake()->randomElement(['1.00', '1.10', '1.20', '1.25', '1.50']),
            'min_price_cents'  => fake()->optional(0.6)->numberBetween(2000, $baseRate),
            'max_price_cents'  => fake()->optional(0.6)->numberBetween($baseRate, 25000),
            'is_active'        => fake()->boolean(80),
            'metadata'         => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withSurge(string $multiplier = '1.25'): static
    {
        return $this->state(['surge_multiplier' => $multiplier]);
    }
}
