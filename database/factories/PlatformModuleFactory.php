<?php

namespace Database\Factories;

use App\Models\PlatformModule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformModule>
 */
class PlatformModuleFactory extends Factory
{
    protected $model = PlatformModule::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));
        $key = Str::slug($name, '_');

        return [
            'key' => $key.'_'.fake()->unique()->numerify('##'),
            'name' => $name,
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['core', 'operations', 'finance', 'crm', 'analytics']),
            'rollout_strategy' => fake()->randomElement(['global', 'role', 'plan', 'zone', 'organization']),
            'is_enabled' => true,
            'is_locked' => false,
            'settings' => null,
            'sort_order' => fake()->numberBetween(1, 200),
        ];
    }
}
