<?php

namespace Database\Factories;

use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceCatalog>
 */
class ServiceCatalogFactory extends Factory
{
    protected $model = ServiceCatalog::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'code' => strtoupper(fake()->unique()->bothify('SVC-###')),
            'name' => Str::title($name),
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'description' => fake()->sentence(),
            'service_type' => fake()->randomElement(['cleaning', 'deep-cleaning', 'move-out', 'windows', 'office']),
            'is_active' => true,
            'requires_quote' => false,
            'requires_manual_validation' => false,
            'is_entreprise' => fake()->boolean(30),
            'default_duration_minutes' => fake()->randomElement([60, 90, 120, 180]),
            'base_price' => fake()->randomFloat(2, 25, 350),
            'sort_order' => fake()->numberBetween(1, 200),
            'settings' => null,
        ];
    }
}
