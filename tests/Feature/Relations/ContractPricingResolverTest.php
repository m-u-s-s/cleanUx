<?php

namespace Tests\Feature\Relations;

use App\Models\ContractRateCard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogV2;
use App\Services\Contracts\ContractPricingResolver;
use App\Services\PricingV2\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPricingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_card_overrides_base_price(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => 10,
        ]);
        $service = ServiceCatalog::factory()->create();
        ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1800,
            'currency' => 'EUR',
        ]);

        $result = app(ContractPricingResolver::class)
            ->resolveCents($contract, $service->id, 2500);

        // Grille prioritaire : prix unitaire négocié, PAS la remise %.
        $this->assertSame(1800, $result['price_cents']);
        $this->assertSame('contract:rate_card', $result['label']);
    }

    public function test_falls_back_to_discount_percent_without_rate_card(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => 20,
        ]);
        $service = ServiceCatalog::factory()->create();

        $result = app(ContractPricingResolver::class)
            ->resolveCents($contract, $service->id, 2500);

        // 2500 - 20% = 2000.
        $this->assertSame(2000, $result['price_cents']);
        $this->assertSame('contract:discount', $result['label']);
    }

    public function test_no_op_without_rate_card_and_zero_discount(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => 0,
        ]);
        $service = ServiceCatalog::factory()->create();

        $result = app(ContractPricingResolver::class)
            ->resolveCents($contract, $service->id, 2500);

        $this->assertSame(2500, $result['price_cents']);
        $this->assertNull($result['label']);
    }

    public function test_pricing_engine_applies_contract_rate_card_via_variables(): void
    {
        config(['pricing_v2.enabled' => true]);

        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'negotiated_discount_percent' => 10,
        ]);
        $service = ServiceCatalog::factory()->create();
        ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1800,
            'currency' => 'EUR',
        ]);

        $serviceV2 = ServiceCatalogV2::create([
            'code' => 'sp4_contract_pricing_v2',
            'name' => 'SP4 Contract Pricing',
            'trade_code' => 'cleaning',
            'base_price_cents' => 5000,
            'currency' => 'EUR',
            'unit' => ServiceCatalogV2::UNIT_FLAT,
            'min_price_cents' => 100,
            'max_price_cents' => 100000,
            'is_active' => true,
            'version' => 1,
        ]);

        $quote = app(PricingEngine::class)->quote($serviceV2->code, [
            '__contract_id' => $contract->id,
            '__service_catalog_id' => $service->id,
        ]);

        $rules = collect((array) $quote->applied_rules);
        $this->assertTrue($rules->contains('code', 'contract:rate_card'));
        $this->assertSame(1800, (int) $quote->computed_price_cents);
    }
}
