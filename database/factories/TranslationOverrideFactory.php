<?php

namespace Database\Factories;

use App\Models\TranslationOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationOverrideFactory extends Factory
{
    protected $model = TranslationOverride::class;

    public function definition(): array
    {
        return [
            'locale' => fake()->randomElement(['fr', 'nl', 'en', 'es', 'de']),
            'group' => fake()->randomElement(['messages', 'validation', 'auth', 'booking']),
            'key' => fake()->unique()->slug(3, '.'),
            'value' => fake()->sentence(),
            'namespace' => null,
            'is_published' => true,
            'updated_by_user_id' => null,
            'metadata' => null,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
