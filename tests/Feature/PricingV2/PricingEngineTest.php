<?php

namespace Tests\Feature\PricingV2;

use App\Models\AbPricingExperiment;
use App\Models\PriceQuote;
use App\Models\PricingRule;
use App\Models\ServiceCatalogV2;
use App\Models\User;
use App\Services\PricingV2\PricingEngine;
use Database\Seeders\PricingV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingV2Seeder::class);
        Config::set('pricing_v2.enabled', true);
    }

    public function test_quote_base_price_with_no_rules_matching(): void
    {
        $row = app(PricingEngine::class)->quote('cleaning_standard', []);

        $this->assertSame(5000, (int) $row->computed_price_cents);
        $this->assertSame('cleaning_standard', $row->service_code);
        $this->assertSame([], (array) $row->applied_rules);
    }

    public function test_quote_applies_per_unit_surface_rule(): void
    {
        $row = app(PricingEngine::class)->quote('cleaning_standard', [
            'surface_m2' => 100,
        ]);

        // base 5000 + 50 cents × 100 m² = 5000 + 5000 = 10000
        $this->assertSame(10000, (int) $row->computed_price_cents);
        $this->assertCount(1, $row->applied_rules);
    }

    public function test_quote_applies_urgency_percent(): void
    {
        $row = app(PricingEngine::class)->quote('cleaning_standard', [
            'urgency' => 'urgent',
        ]);

        // base 5000 × 1.20 = 6000
        $this->assertSame(6000, (int) $row->computed_price_cents);
    }

    public function test_quote_applies_recurrent_discount(): void
    {
        $row = app(PricingEngine::class)->quote('cleaning_standard', [
            'is_recurrent' => true,
        ]);

        // base 5000 × 0.90 = 4500 but min_price=3000 so 4500 stays
        $this->assertSame(4500, (int) $row->computed_price_cents);
    }

    public function test_quote_stacks_multiple_rules_in_priority_order(): void
    {
        // Surface (10) → urgency (20) → recurrent (-10%, priority 30)
        $row = app(PricingEngine::class)->quote('cleaning_standard', [
            'surface_m2' => 100,
            'urgency' => 'urgent',
            'is_recurrent' => true,
        ]);

        // base 5000 + 50×100=5000 → 10000
        // × 1.20 (urgency) → 12000
        // × 0.90 (recurrent) → 10800
        $this->assertSame(10800, (int) $row->computed_price_cents);
        $this->assertCount(3, $row->applied_rules);
    }

    public function test_quote_clamps_to_max(): void
    {
        $service = ServiceCatalogV2::query()->where('code', 'cleaning_standard')->first();
        $service->forceFill(['max_price_cents' => 8000])->save();

        $row = app(PricingEngine::class)->quote('cleaning_standard', [
            'surface_m2' => 500,
        ]);

        $this->assertSame(8000, (int) $row->computed_price_cents);
    }

    public function test_quote_clamps_to_min(): void
    {
        $service = ServiceCatalogV2::query()->where('code', 'cleaning_standard')->first();
        $service->forceFill(['min_price_cents' => 5500])->save();

        $row = app(PricingEngine::class)->quote('cleaning_standard', [
            'is_recurrent' => true,
        ]);

        // 5000 × 0.90 = 4500, clamped to min 5500
        $this->assertSame(5500, (int) $row->computed_price_cents);
    }

    public function test_quote_is_idempotent_with_same_key(): void
    {
        $svc = app(PricingEngine::class);
        $a = $svc->quote('cleaning_standard', ['urgency' => 'urgent'], idempotencyKey: 'k1');
        $b = $svc->quote('cleaning_standard', ['urgency' => 'normal'], idempotencyKey: 'k1');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, PriceQuote::count());
    }

    public function test_quote_rejects_unknown_service(): void
    {
        $this->expectException(ValidationException::class);
        app(PricingEngine::class)->quote('nonexistent', []);
    }

    public function test_preview_returns_without_persisting(): void
    {
        $preview = app(PricingEngine::class)->preview('cleaning_standard', ['urgency' => 'urgent']);

        $this->assertSame(6000, (int) $preview['computed_price_cents']);
        $this->assertSame(0, PriceQuote::count());
    }

    public function test_sanitize_drops_unknown_variables(): void
    {
        // 'arbitrary_key' is not in whitelist → dropped before DSL eval
        $row = app(PricingEngine::class)->quote('cleaning_standard', [
            'arbitrary_key' => 'anything',
            'urgency' => 'urgent',
        ]);

        $this->assertArrayNotHasKey('arbitrary_key', (array) $row->variables_snapshot);
        $this->assertSame('urgent', $row->variables_snapshot['urgency']);
    }

    public function test_ab_experiment_assigns_deterministic_variant(): void
    {
        AbPricingExperiment::create([
            'code' => 'test_exp',
            'name' => 'Test exp',
            'service_codes' => ['cleaning_standard'],
            'variants' => [
                ['label' => 'A', 'rules_override' => []],
                ['label' => 'B', 'rules_override' => []],
            ],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $user = User::factory()->client()->create();
        $svc = app(PricingEngine::class);

        $a = $svc->quote('cleaning_standard', [], $user);
        $b = $svc->quote('cleaning_standard', [], $user, idempotencyKey: 'second');

        $this->assertSame($a->variant_label, $b->variant_label);
        $this->assertContains($a->variant_label, ['A', 'B']);
    }
}
