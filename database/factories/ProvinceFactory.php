<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Province';

        return [
            'country_id' => Country::factory(),
            'region_id' => Region::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PR##')),
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => fake()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
