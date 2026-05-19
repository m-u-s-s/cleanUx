<?php

namespace Tests\Feature;

use App\Models\Trade;
use App\Services\Pricing\SurgePricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class TradeBusinessMultipliersPricingTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // reset
        parent::tearDown();
    }

    protected function makeTrade(array $overrides = []): Trade
    {
        return Trade::create(array_merge([
            'slug'                  => 'serrurerie',
            'code'                  => 'LOCK',
            'name'                  => 'Serrurerie',
            'is_active'             => true,
            'sort_order'            => 10,
            'emergency_multiplier'  => 1.00,
            'night_multiplier'      => 1.00,
            'weekend_multiplier'    => 1.00,
        ], $overrides));
    }

    public function test_emergency_multiplier_replaces_generic_asap_when_present(): void
    {
        // Force un mardi matin pour neutraliser weekend/temporal
        Carbon::setTestNow(Carbon::create(2026, 5, 19, 10, 0, 0));

        $context = $this->createCoverageContext();
        $trade = $this->makeTrade(['emergency_multiplier' => 3.00]);

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], [
            'trade_id'     => $trade->id,
            'booking_mode' => 'asap',
        ]);

        // L'ASAP générique (1.25) doit être annulé et remplacé par 3.0
        $this->assertSame(1.0, $result['factors']['asap']);
        $this->assertSame(3.0, $result['factors']['trade_business']);
    }

    public function test_night_multiplier_applies_between_22_and_6(): void
    {
        // Mardi 23h00 → nuit
        Carbon::setTestNow(Carbon::create(2026, 5, 19, 23, 0, 0));

        $context = $this->createCoverageContext();
        $trade = $this->makeTrade(['night_multiplier' => 2.00]);

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], ['trade_id' => $trade->id]);

        $this->assertSame(2.0, $result['factors']['trade_business']);
    }

    public function test_night_multiplier_not_applied_during_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 19, 14, 0, 0));

        $context = $this->createCoverageContext();
        $trade = $this->makeTrade(['night_multiplier' => 2.00]);

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], ['trade_id' => $trade->id]);

        $this->assertSame(1.0, $result['factors']['trade_business']);
    }

    public function test_weekend_multiplier_applies_on_saturday(): void
    {
        // 2026-05-23 = samedi
        Carbon::setTestNow(Carbon::create(2026, 5, 23, 11, 0, 0));

        $context = $this->createCoverageContext();
        $trade = $this->makeTrade(['weekend_multiplier' => 1.50]);

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], ['trade_id' => $trade->id]);

        $this->assertSame(1.5, $result['factors']['trade_business']);
    }

    public function test_night_and_weekend_stack_together(): void
    {
        // Samedi 23h → night × weekend
        Carbon::setTestNow(Carbon::create(2026, 5, 23, 23, 0, 0));

        $context = $this->createCoverageContext();
        $trade = $this->makeTrade([
            'night_multiplier'   => 2.00,
            'weekend_multiplier' => 1.50,
        ]);

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], ['trade_id' => $trade->id]);

        // 2.00 × 1.50 = 3.00
        $this->assertSame(3.0, $result['factors']['trade_business']);
    }

    public function test_no_trade_business_factor_when_all_multipliers_are_one(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 23, 23, 0, 0));

        $context = $this->createCoverageContext();
        $trade = $this->makeTrade(); // tout à 1.00

        $engine = app(SurgePricingEngine::class);
        $result = $engine->calculate(100.0, $context['zone'], ['trade_id' => $trade->id]);

        $this->assertSame(1.0, $result['factors']['trade_business']);
    }
}
