<?php

namespace Tests\Feature\Relations;

use App\Exceptions\ContractPolicyException;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Services\Contracts\ContractPolicyEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPolicyEnforcerTest extends TestCase
{
    use RefreshDatabase;

    private function contract(array $attrs = []): OrganizationContract
    {
        return OrganizationContract::factory()->create(array_merge([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
        ], $attrs));
    }

    public function test_blocks_when_service_not_allowed(): void
    {
        $allowed = ServiceCatalog::factory()->create();
        $other = ServiceCatalog::factory()->create();
        $contract = $this->contract(['allowed_service_catalog_ids' => [$allowed->id]]);

        $this->expectException(ContractPolicyException::class);
        app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => $other->id],
            $contract,
        );
    }

    public function test_blocks_when_po_required_and_missing(): void
    {
        $contract = $this->contract(['requires_purchase_order' => true]);

        $this->expectException(ContractPolicyException::class);
        app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id],
            $contract,
        );
    }

    public function test_forces_default_cost_center_when_absent(): void
    {
        $contract = $this->contract(['default_cost_center' => 'CC-42']);

        $out = app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id],
            $contract,
        );

        $this->assertSame('CC-42', $out['cost_center']);
    }

    public function test_flags_manual_approval(): void
    {
        $contract = $this->contract(['approval_mode' => 'manual']);

        $out = app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id, 'purchase_order_number' => 'PO-1'],
            $contract,
        );

        $this->assertTrue($out['entreprise_approval_required']);
    }

    public function test_passes_when_all_satisfied(): void
    {
        $service = ServiceCatalog::factory()->create();
        $contract = $this->contract([
            'allowed_service_catalog_ids' => [$service->id],
            'requires_purchase_order' => true,
            'approval_mode' => 'auto',
        ]);

        $out = app(ContractPolicyEnforcer::class)->enforceForBookingData(
            ['service_catalog_id' => $service->id, 'purchase_order_number' => 'PO-9'],
            $contract,
        );

        $this->assertArrayNotHasKey('entreprise_approval_required', $out);
        $this->assertSame('PO-9', $out['purchase_order_number']);
    }
}
