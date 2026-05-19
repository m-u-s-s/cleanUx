<?php

namespace Tests\Feature;

use App\Models\Trade;
use App\Models\TradeZoneSetting;
use App\Services\Pricing\SurgePricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class SurgePricingTradeZoneMultiplierTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_trade_zone_multiplier_is_applied_when_present(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'peinture', 'code' => 'PAINT', 'name' => 'Peinture',
            'is_active' => true, 'sort_order' => 10,
        ]);

        TradeZoneSetting::create([
            'trade_id'         => $trade->id,
            'service_zone_id'  => $context['zone']->id,
            'is_active'        => true,
            'price_multiplier' => 1.50,
        ]);

        /** @var SurgePricingEngine $engine */
        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], ['trade_id' => $trade->id]);

        $this->assertSame(1.5, $result['factors']['trade_zone']);
        $this->assertGreaterThanOrEqual(150.0, $result['final_price']);
    }

    public function test_trade_zone_multiplier_defaults_to_one_when_no_setting(): void
    {
        $context = $this->createCoverageContext();
        $trade = Trade::create([
            'slug' => 'jardinage', 'code' => 'GARDEN', 'name' => 'Jardinage',
            'is_active' => true, 'sort_order' => 10,
        ]);

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], ['trade_id' => $trade->id]);

        $this->assertSame(1.0, $result['factors']['trade_zone']);
    }

    public function test_no_trade_in_context_does_not_apply_trade_multiplier(): void
    {
        $context = $this->createCoverageContext();

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], []);

        $this->assertSame(1.0, $result['factors']['trade_zone']);
    }
}
