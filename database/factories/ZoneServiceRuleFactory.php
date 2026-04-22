<?php

namespace Database\Factories;

use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZoneServiceRule>
 */
class ZoneServiceRuleFactory extends Factory
{
    protected $model = ZoneServiceRule::class;

    public function definition(): array
    {
        return [
            'service_zone_id' => ServiceZone::factory(),
            'service_catalog_id' => ServiceCatalog::factory(),
            'is_enabled' => true,
            'requires_manual_validation' => fake()->boolean(20),
            'base_price_override' => fake()->optional()->randomFloat(2, 30, 400),
            'price_multiplier' => fake()->optional()->randomFloat(2, 0.8, 1.8),
            'minimum_notice_hours' => fake()->optional()->randomElement([2, 4, 12, 24, 48]),
            'maximum_daily_capacity' => fake()->optional()->numberBetween(1, 20),
            'settings' => null,
        ];
    }
}
