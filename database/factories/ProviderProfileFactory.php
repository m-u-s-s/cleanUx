<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderProfileFactory extends Factory
{
    protected $model = ProviderProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'active',
            'hourly_rate' => fake()->randomFloat(2, 15, 45),
            'commission_rate' => 0.15,
            'current_lat' => fake()->latitude(50.0, 51.5),
            'current_lng' => fake()->longitude(3.0, 5.5),
            'is_online' => false,
            'rating_avg' => fake()->optional()->randomFloat(2, 3.5, 5.0),
            'rating_count' => fake()->numberBetween(0, 200),
            'rating_distribution' => ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0],
            'rating_dimensions' => [],
            'skills' => [],
            'settings' => [],
            'metadata' => [],
        ];
    }
}
