<?php

namespace Database\Factories;

use App\Models\ServiceCatalogV2;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceCatalogV2Factory extends Factory
{
    protected $model = ServiceCatalogV2::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'code' => Str::upper(Str::slug($name, '_')).'_V2_'.fake()->unique()->numberBetween(1, 9999),
            'name' => $name,
            'description' => fake()->sentence(),
            'trade_code' => fake()->randomElement(['cleaning', 'painting', 'babysitting', 'roofing']),
            'base_price_cents' => fake()->numberBetween(2000, 50000),
            'currency' => 'EUR',
            'unit' => fake()->randomElement([
                ServiceCatalogV2::UNIT_PER_HOUR,
                ServiceCatalogV2::UNIT_PER_VISIT,
                ServiceCatalogV2::UNIT_FLAT,
            ]),
            'min_price_cents' => fake()->numberBetween(500, 1000),
            'max_price_cents' => fake()->numberBetween(50000, 100000),
            'is_active' => true,
            'version' => 1,
            'parent_version_id' => null,
            'locale_overrides' => null,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
