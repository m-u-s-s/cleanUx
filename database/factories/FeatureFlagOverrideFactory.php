<?php

namespace Database\Factories;

use App\Models\FeatureFlagOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFlagOverrideFactory extends Factory
{
    protected $model = FeatureFlagOverride::class;

    public function definition(): array
    {
        return [
            'flag_key' => 'feature-'.fake()->unique()->slug(2),
            'is_enabled' => fake()->boolean(),
            'override_config' => null,
            'reason' => fake()->sentence(),
            'updated_by_user_id' => null,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn () => ['is_enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
