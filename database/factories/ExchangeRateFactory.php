<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        $pairs = [
            ['EUR', 'USD'],
            ['EUR', 'GBP'],
            ['EUR', 'CHF'],
            ['EUR', 'MAD'],
            ['USD', 'EUR'],
        ];

        [$base, $quote] = fake()->randomElement($pairs);

        return [
            'base_currency' => $base,
            'quote_currency' => $quote,
            'rate' => number_format(fake()->randomFloat(4, 0.5, 2.0), 8),
            'source' => ExchangeRate::SOURCE_MOCK,
            'fetched_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
            'valid_from' => now()->startOfDay(),
            'valid_until' => now()->endOfDay(),
            'idempotency_key' => Str::uuid()->toString(),
            'metadata' => [],
        ];
    }

    public function stale(): static
    {
        return $this->state(fn () => [
            'fetched_at' => now()->subHours(25),
        ]);
    }
}
