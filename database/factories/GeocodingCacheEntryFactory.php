<?php

namespace Database\Factories;

use App\Models\GeocodingCacheEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GeocodingCacheEntryFactory extends Factory
{
    protected $model = GeocodingCacheEntry::class;

    public function definition(): array
    {
        $address = fake()->streetAddress() . ', ' . fake()->city() . ', Belgium';

        return [
            'provider' => fake()->randomElement(['mock', 'google', 'mapbox']),
            'address_hash' => hash('sha256', $address),
            'address_input' => $address,
            'country_code' => 'BE',
            'latitude' => fake()->latitude(49.5, 51.5),
            'longitude' => fake()->longitude(2.5, 6.5),
            'formatted_address' => $address,
            'place_id' => null,
            'postal_code' => fake()->numerify('####'),
            'locality' => fake()->city(),
            'components' => [],
            'raw' => [],
            'expires_at' => now()->addDays(30),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
