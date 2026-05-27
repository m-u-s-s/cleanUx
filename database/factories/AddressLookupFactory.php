<?php

namespace Database\Factories;

use App\Models\AddressLookup;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressLookupFactory extends Factory
{
    protected $model = AddressLookup::class;

    public function definition(): array
    {
        $query = fake()->city() . ', Belgique';

        return [
            'provider'     => 'mock',
            'query_hash'   => sha1($query . '|BE'),
            'query'        => $query,
            'country_code' => 'BE',
            'results'      => [['label' => $query, 'lat' => 50.85, 'lng' => 4.35]],
            'result_count' => 1,
            'queried_at'   => now(),
            'expires_at'   => now()->addHours(24),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }
}
