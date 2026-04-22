<?php

namespace Database\Factories;

use App\Models\Commune;
use App\Models\Country;
use App\Models\PostalCode;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostalCode>
 */
class PostalCodeFactory extends Factory
{
    protected $model = PostalCode::class;

    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'region_id' => Region::factory(),
            'province_id' => Province::factory(),
            'commune_id' => Commune::factory(),
            'code' => fake()->unique()->numerify('####'),
            'city_name' => fake()->city(),
            'latitude' => fake()->latitude(49.4, 51.6),
            'longitude' => fake()->longitude(2.5, 6.4),
            'is_active' => true,
        ];
    }
}
