<?php

namespace Database\Factories;

use App\Models\RecurringTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RecurringTemplateFactory extends Factory
{
    protected $model = RecurringTemplate::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name'                      => $name,
            'slug'                      => Str::slug($name) . '-' . Str::random(4),
            'description'               => fake()->sentence(),
            'category'                  => fake()->randomElement([
                RecurringTemplate::CATEGORY_OFFICE,
                RecurringTemplate::CATEGORY_RESIDENTIAL,
                RecurringTemplate::CATEGORY_RETAIL,
            ]),
            'icon'                      => null,
            'is_system'                 => false,
            'owner_user_id'             => null,
            'owner_organization_id'     => null,
            'default_service_catalog_id' => null,
            'frequency'                 => RecurringTemplate::FREQ_WEEKLY,
            'interval'                  => 1,
            'days'                      => ['monday', 'thursday'],
            'default_time'              => '09:00',
            'default_duration_minutes'  => 120,
            'payload'                   => null,
            'usage_count'               => 0,
            'is_active'                 => true,
            'display_order'             => fake()->numberBetween(1, 100),
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
