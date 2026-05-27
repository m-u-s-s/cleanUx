<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\MarketLaunchReadiness;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketLaunchReadinessFactory extends Factory
{
    protected $model = MarketLaunchReadiness::class;

    public function definition(): array
    {
        return [
            'country_id'           => fn () => Country::factory()->create()->id,
            'catalog_ready'        => false,
            'booking_ready'        => false,
            'mission_ready'        => false,
            'billing_ready'        => false,
            'partner_network_ready' => false,
            'compliance_ready'     => false,
            'support_ready'        => false,
            'notes'                => null,
            'last_audited_at'      => null,
            'metadata'             => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'catalog_ready'        => true,
            'booking_ready'        => true,
            'mission_ready'        => true,
            'billing_ready'        => true,
            'partner_network_ready' => true,
            'compliance_ready'     => true,
            'support_ready'        => true,
            'last_audited_at'      => now(),
        ]);
    }
}
