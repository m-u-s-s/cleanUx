<?php

namespace Database\Factories;

use App\Models\CurrencyConversion;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CurrencyConversion>
 */
class CurrencyConversionFactory extends Factory
{
    protected $model = CurrencyConversion::class;

    public function definition(): array
    {
        $sourceAmount = fake()->numberBetween(1000, 100000);
        $rate         = fake()->randomFloat(6, 0.8, 1.5);
        $targetAmount = (int) round($sourceAmount * $rate);

        return [
            'source_amount_cents' => $sourceAmount,
            'source_currency'     => 'EUR',
            'target_amount_cents' => $targetAmount,
            'target_currency'     => fake()->randomElement(['GBP', 'USD', 'CHF']),
            'exchange_rate_id'    => ExchangeRate::factory(),
            'rate_used'           => $rate,
            'fee_percent'         => '0.5000',
            'source_type'         => 'booking',
            'source_id'           => null,
            'user_id'             => User::factory(),
            'idempotency_key'     => Str::uuid()->toString(),
            'converted_at'        => now(),
            'metadata'            => null,
        ];
    }
}
