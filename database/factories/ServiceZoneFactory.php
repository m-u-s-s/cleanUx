<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\ServiceZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceZone>
 */
class ServiceZoneFactory extends Factory
{
    protected $model = ServiceZone::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Zone';

        return [
            'country_id' => Country::factory(),
            'region_id' => null,
            'province_id' => null,
            'commune_id' => null,
            'parent_zone_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('ZONE-###')),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'coverage_type' => fake()->randomElement(['region', 'province', 'commune', 'postal_code', 'custom']),
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
            'priority' => fake()->numberBetween(1, 500),
            'minimum_notice_hours' => fake()->randomElement([2, 4, 12, 24, 48]),
            'maximum_daily_jobs' => fake()->optional()->numberBetween(1, 20),
            'travel_surcharge' => fake()->randomFloat(2, 0, 50),
            'time_buffer_minutes' => fake()->randomElement([0, 15, 30, 45, 60]),
            'metadata' => null,
            'notes' => fake()->optional()->sentence(),
            'activated_at' => now(),
            'deactivated_at' => null,
        ];
    }
}
