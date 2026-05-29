<?php

namespace Database\Factories;

use App\Models\PriceQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PriceQuote>
 */
class PriceQuoteFactory extends Factory
{
    protected $model = PriceQuote::class;

    public function definition(): array
    {
        $baseCents = fake()->numberBetween(5000, 50000);
        $computedCents = (int) round($baseCents * fake()->randomFloat(2, 0.8, 1.3));
        $tradeCodes = ['cleaning', 'painting', 'babysitting', 'roofing', 'gardening'];

        return [
            'service_code' => 'svc-'.fake()->slug(2),
            'trade_code' => fake()->randomElement($tradeCodes),
            'base_price_cents' => $baseCents,
            'computed_price_cents' => $computedCents,
            'currency' => 'EUR',
            'variables_snapshot' => ['duration_minutes' => fake()->randomElement([60, 90, 120])],
            'applied_rules' => [],
            'variant_label' => null,
            'user_id' => User::factory(),
            'booking_id' => null,
            'idempotency_key' => Str::uuid()->toString(),
            'quoted_at' => now(),
            'metadata' => null,
        ];
    }
}
