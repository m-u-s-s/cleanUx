<?php

namespace Database\Factories;

use App\Models\ServiceCatalog;
use App\Models\ServiceOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceOption>
 */
class ServiceOptionFactory extends Factory
{
    protected $model = ServiceOption::class;

    public function definition(): array
    {
        $types = ['number', 'select', 'boolean', 'text'];
        $type = fake()->randomElement($types);

        return [
            'service_catalog_id' => ServiceCatalog::factory(),
            'slug' => Str::slug(fake()->unique()->words(2, true)),
            'label' => fake()->sentence(2),
            'help_text' => fake()->optional()->sentence(),
            'type' => $type,
            'values' => $type === 'select' ? ['option1', 'option2', 'option3'] : null,
            'unit' => $type === 'number' ? fake()->randomElement(['m2', 'pièce', 'heure']) : null,
            'default_value_num' => $type === 'number' ? fake()->numberBetween(1, 100) : null,
            'default_value_str' => null,
            'is_required' => fake()->boolean(40),
            'price_modifier' => fake()->randomElement(['flat', 'per_unit', 'percent', null]),
            'price_modifier_value' => fake()->optional()->randomFloat(2, 1, 50),
            'min_value' => null,
            'max_value' => null,
            'step' => null,
        ];
    }
}
