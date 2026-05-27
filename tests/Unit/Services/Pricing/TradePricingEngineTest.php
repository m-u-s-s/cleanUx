<?php

namespace Tests\Unit\Services\Pricing;

use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\Pricing\TradePricingEngine;
use Tests\TestCase;

/**
 * Unit tests for TradePricingEngine::estimate().
 *
 * Zone-pricing tests use StubbedTradePricingEngine (defined at bottom of
 * file) which overrides the protected resolveZonePricing() method so the
 * Eloquent query is never executed. All other tests use the real engine.
 */
class TradePricingEngineTest extends TestCase
{
    private TradePricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new TradePricingEngine();
    }

    // ──────────────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────────────

    private function makeService(array $attrs = [], ?array $tradeAttrs = null): ServiceCatalog
    {
        $service = new ServiceCatalog($attrs);
        $trade   = $tradeAttrs !== null ? new Trade($tradeAttrs) : null;
        $service->setRelation('trade', $trade);
        return $service;
    }

    private function engineWithZone(TradeZonePricing $zone): TradePricingEngine
    {
        return new class($zone) extends TradePricingEngine {
            public function __construct(private readonly TradeZonePricing $stub) {}

            protected function resolveZonePricing(?int $tradeId, ?int $serviceZoneId): ?TradeZonePricing
            {
                return $this->stub;
            }
        };
    }

    private function makeZone(array $attrs): TradeZonePricing
    {
        return new TradeZonePricing(array_merge([
            'base_rate_cents'  => 2000,
            'surge_multiplier' => '1.00',
            'min_price_cents'  => null,
            'max_price_cents'  => null,
            'is_active'        => true,
        ], $attrs));
    }

    // ──────────────────────────────────────────────────────────────────
    // 1. Hourly billing
    // ──────────────────────────────────────────────────────────────────

    public function test_hourly_billing_uses_duration_hours(): void
    {
        $service = $this->makeService(['base_price' => '20.00', 'billing_unit' => 'hourly']);

        $result = $this->engine->estimate($service, ['duration_hours' => 3]);

        $this->assertSame('hourly', $result['billing_unit']);
        $this->assertSame(3.0, $result['quantity']);
        $this->assertSame(60.0, $result['subtotal']);
    }

    public function test_hourly_billing_accepts_french_key_nombre_heures(): void
    {
        $service = $this->makeService(['base_price' => '10.00', 'billing_unit' => 'hourly']);

        $result = $this->engine->estimate($service, ['nombre_heures' => 4]);

        $this->assertSame(40.0, $result['subtotal']);
    }

    public function test_hourly_billing_defaults_to_two_hours_when_no_answer(): void
    {
        $service = $this->makeService(['base_price' => '15.00', 'billing_unit' => 'hourly']);

        $result = $this->engine->estimate($service, []);

        $this->assertSame(2.0, $result['quantity']);
        $this->assertSame(30.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. per_m2 billing
    // ──────────────────────────────────────────────────────────────────

    public function test_per_m2_billing_uses_surface_m2(): void
    {
        $service = $this->makeService(['base_price' => '5.00', 'billing_unit' => 'per_m2']);

        $result = $this->engine->estimate($service, ['surface_m2' => 50]);

        $this->assertSame('per_m2', $result['billing_unit']);
        $this->assertSame(50.0, $result['quantity']);
        $this->assertSame(250.0, $result['subtotal']);
    }

    public function test_per_m2_billing_accepts_surface_alias(): void
    {
        $service = $this->makeService(['base_price' => '4.00', 'billing_unit' => 'per_m2']);

        $result = $this->engine->estimate($service, ['surface' => 25]);

        $this->assertSame(100.0, $result['subtotal']);
    }

    public function test_sqm_legacy_alias_normalises_to_per_m2(): void
    {
        $service = $this->makeService(['base_price' => '3.00', 'billing_unit' => 'sqm']);

        $result = $this->engine->estimate($service, ['surface_m2' => 10]);

        $this->assertSame('per_m2', $result['billing_unit']);
        $this->assertSame(30.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 3. fixed billing
    // ──────────────────────────────────────────────────────────────────

    public function test_fixed_billing_quantity_is_always_one_regardless_of_answers(): void
    {
        $service = $this->makeService(['base_price' => '150.00', 'billing_unit' => 'fixed']);

        $result = $this->engine->estimate($service, ['duration_hours' => 99, 'surface_m2' => 999]);

        $this->assertSame('fixed', $result['billing_unit']);
        $this->assertSame(1.0, $result['quantity']);
        $this->assertSame(150.0, $result['subtotal']);
    }

    public function test_flat_legacy_alias_normalises_to_fixed(): void
    {
        $service = $this->makeService(['base_price' => '80.00', 'billing_unit' => 'flat']);

        $result = $this->engine->estimate($service, []);

        $this->assertSame('fixed', $result['billing_unit']);
        $this->assertSame(80.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 4. per_item billing
    // ──────────────────────────────────────────────────────────────────

    public function test_per_item_billing_uses_quantity(): void
    {
        $service = $this->makeService(['base_price' => '12.00', 'billing_unit' => 'per_item']);

        $result = $this->engine->estimate($service, ['quantity' => 5]);

        $this->assertSame('per_item', $result['billing_unit']);
        $this->assertSame(5.0, $result['quantity']);
        $this->assertSame(60.0, $result['subtotal']);
    }

    public function test_per_item_billing_accepts_nombre_items_alias(): void
    {
        $service = $this->makeService(['base_price' => '10.00', 'billing_unit' => 'per_item']);

        $result = $this->engine->estimate($service, ['nombre_items' => 3]);

        $this->assertSame(30.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 5. Trade-level billing_unit fallback
    // ──────────────────────────────────────────────────────────────────

    public function test_falls_back_to_trade_billing_unit_when_service_has_none(): void
    {
        $service = $this->makeService(
            ['base_price' => '6.00'],
            ['billing_unit' => 'per_m2'],
        );

        $result = $this->engine->estimate($service, ['surface_m2' => 10]);

        $this->assertSame('per_m2', $result['billing_unit']);
        $this->assertSame(60.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 6. Zone pricing overrides catalog base price
    // ──────────────────────────────────────────────────────────────────

    public function test_zone_pricing_overrides_when_catalog_has_no_price(): void
    {
        $service = $this->makeService(
            ['base_price' => '0', 'billing_unit' => 'hourly', 'trade_id' => 1],
            ['id' => 1],
        );

        $zone   = $this->makeZone(['base_rate_cents' => 3000]); // 30 EUR/h
        $engine = $this->engineWithZone($zone);

        $result = $engine->estimate($service, ['duration_hours' => 2], 5);

        $this->assertTrue($result['zone_pricing_applied']);
        $this->assertSame(30.0, $result['unit_price']);
        $this->assertSame(60.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 7. Surge multiplier
    // ──────────────────────────────────────────────────────────────────

    public function test_surge_multiplier_scales_subtotal(): void
    {
        $service = $this->makeService(
            ['base_price' => '0', 'billing_unit' => 'hourly', 'trade_id' => 1],
            ['id' => 1],
        );

        $zone   = $this->makeZone(['base_rate_cents' => 2000, 'surge_multiplier' => '1.50']);
        $engine = $this->engineWithZone($zone);

        // 20 EUR × 2 hours × 1.5 surge = 60 EUR
        $result = $engine->estimate($service, ['duration_hours' => 2], 2);

        $this->assertSame(1.5, $result['surge_multiplier']);
        $this->assertSame(60.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 8. Min price floor capping
    // ──────────────────────────────────────────────────────────────────

    public function test_min_price_floor_is_applied_when_subtotal_is_below_minimum(): void
    {
        $service = $this->makeService(
            ['base_price' => '5.00', 'billing_unit' => 'hourly', 'trade_id' => 2],
            ['id' => 2],
        );

        $zone   = $this->makeZone(['base_rate_cents' => 500, 'min_price_cents' => 2000]);
        $engine = $this->engineWithZone($zone);

        // 5 EUR × 1 h = 5 EUR → clamped to 20 EUR minimum
        $result = $engine->estimate($service, ['duration_hours' => 1], 3);

        $this->assertSame(20.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 9. Max price ceiling capping
    // ──────────────────────────────────────────────────────────────────

    public function test_max_price_ceiling_is_applied_when_subtotal_exceeds_maximum(): void
    {
        $service = $this->makeService(
            ['base_price' => '50.00', 'billing_unit' => 'per_m2', 'trade_id' => 3],
            ['id' => 3],
        );

        $zone   = $this->makeZone(['base_rate_cents' => 5000, 'max_price_cents' => 10000]);
        $engine = $this->engineWithZone($zone);

        // 50 EUR × 10 m² = 500 EUR → clamped to 100 EUR maximum
        $result = $engine->estimate($service, ['surface_m2' => 10], 4);

        $this->assertSame(100.0, $result['subtotal']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 10. Response shape completeness
    // ──────────────────────────────────────────────────────────────────

    public function test_response_contains_all_required_keys_with_correct_types(): void
    {
        $service = $this->makeService(['base_price' => '20.00', 'billing_unit' => 'hourly']);

        $result = $this->engine->estimate($service, []);

        foreach (['billing_unit', 'unit_price', 'quantity', 'surge_multiplier', 'subtotal', 'currency', 'zone_pricing_applied'] as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }

        $this->assertSame('EUR', $result['currency']);
        $this->assertFalse($result['zone_pricing_applied']);
        $this->assertSame(1.0, $result['surge_multiplier']);
    }

    public function test_unknown_billing_unit_defaults_to_hourly(): void
    {
        $service = $this->makeService(['base_price' => '10.00', 'billing_unit' => 'unknown_unit']);

        $result = $this->engine->estimate($service, ['duration_hours' => 2]);

        $this->assertSame('hourly', $result['billing_unit']);
        $this->assertSame(20.0, $result['subtotal']);
    }
}
