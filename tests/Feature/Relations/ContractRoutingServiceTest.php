<?php

namespace Tests\Feature\Relations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Services\Contracts\ContractRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_partner_org_and_contract_to_booking_data(): void
    {
        $provider = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => $provider->id,
        ]);

        $data = ['service_catalog_id' => 1];
        $out = app(ContractRoutingService::class)->applyToBookingData($data, $contract);

        $this->assertSame($provider->id, $out['assigned_provider_organization_id']);
        $this->assertSame($contract->id, $out['organization_contract_id']);
    }

    public function test_does_not_override_explicit_client_choice(): void
    {
        $provider = OrganizationAccount::factory()->create();
        $otherOrg = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => $provider->id,
        ]);

        // Le client a explicitement choisi une AUTRE org (SP3) → le contrat ne l'écrase pas,
        // mais stampe quand même le contrat (traçabilité).
        $data = ['assigned_provider_organization_id' => $otherOrg->id];
        $out = app(ContractRoutingService::class)->applyToBookingData($data, $contract);

        $this->assertSame($otherOrg->id, $out['assigned_provider_organization_id']);
        $this->assertSame($contract->id, $out['organization_contract_id']);
    }

    public function test_does_not_override_explicit_preferred_provider(): void
    {
        $provider = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => $provider->id,
        ]);

        $data = ['preferred_provider_user_id' => 999];
        $out = app(ContractRoutingService::class)->applyToBookingData($data, $contract);

        // Un presta précis choisi (SP2) prime : pas d'org de contrat imposée.
        $this->assertArrayNotHasKey('assigned_provider_organization_id', $out);
        $this->assertSame($contract->id, $out['organization_contract_id']);
    }
}
