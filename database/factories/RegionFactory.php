<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Region';

        return [
            'country_id' => Country::factory(),
            'code' => strtoupper(fake()->unique()->bothify('RG##')),
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(['region', 'district', 'territory']),
            'sort_order' => fake()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
