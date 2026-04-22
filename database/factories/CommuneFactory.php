<?php

namespace Database\Factories;

use App\Models\Commune;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Commune>
 */
class CommuneFactory extends Factory
{
    protected $model = Commune::class;

    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'country_id' => Country::factory(),
            'region_id' => Region::factory(),
            'province_id' => Province::factory(),
            'nis_code' => fake()->unique()->numerify('#####'),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
