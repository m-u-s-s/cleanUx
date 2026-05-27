<?php

namespace Database\Factories;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZoneSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeZoneSettingFactory extends Factory
{
    protected $model = TradeZoneSetting::class;

    public function definition(): array
    {
        return [
            'trade_id'         => fn () => Trade::factory()->create()->id,
            'service_zone_id'  => fn () => ServiceZone::factory()->create()->id,
            'is_active'        => true,
            'price_multiplier' => 1.00,
            'notes'            => null,
            'created_by'       => null,
            'updated_by'       => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withMultiplier(float $multiplier): static
    {
        return $this->state(fn () => ['price_multiplier' => $multiplier]);
    }
}
