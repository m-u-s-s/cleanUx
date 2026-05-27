<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        static $codes = ['EUR', 'USD', 'GBP', 'CHF', 'NOK', 'SEK', 'DKK', 'PLN', 'CZK', 'HUF'];

        return [
            'code'       => fake()->unique()->randomElement($codes),
            'name'       => fake()->word(),
            'symbol'     => fake()->randomElement(['EUR', '$', 'GBP']),
            'decimals'   => 2,
            'is_active'  => true,
            'sort_order' => fake()->numberBetween(1, 100),
            'metadata'   => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
