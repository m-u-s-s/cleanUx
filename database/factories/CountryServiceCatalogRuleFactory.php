<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\CountryServiceCatalogRule;
use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryServiceCatalogRuleFactory extends Factory
{
    protected $model = CountryServiceCatalogRule::class;

    public function definition(): array
    {
        return [
            'country_id'                  => fn () => Country::factory()->create()->id,
            'service_catalog_id'          => fn () => ServiceCatalog::factory()->create()->id,
            'is_enabled'                  => true,
            'requires_manual_validation'  => false,
            'requires_quote'              => false,
            'minimum_notice_hours'        => 24,
            'sla_response_hours'          => 4,
            'sla_resolution_hours'        => 48,
            'default_team_id'             => null,
            'default_partner_id'          => null,
            'pricing_multiplier'          => 1.00,
            'settings'                    => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
