<?php

namespace Tests\Feature\Relations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Services\Contracts\ContractResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_active_contract_for_client_org_service_and_date(): void
    {
        $client = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
            'allowed_service_catalog_ids' => [$service->id],
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $service->id, null, now()->toDateString());

        $this->assertNotNull($resolved);
        $this->assertSame($contract->id, $resolved->id);
    }

    public function test_returns_null_when_service_not_allowed(): void
    {
        $client = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $allowed = ServiceCatalog::factory()->create();
        $other = ServiceCatalog::factory()->create();

        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'allowed_service_catalog_ids' => [$allowed->id],
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $other->id, null, now()->toDateString());

        $this->assertNull($resolved);
    }

    public function test_returns_null_without_provider_org_or_inactive_or_out_of_window(): void
    {
        $client = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        // Pas de provider_organization_id → inutilisable pour le routage.
        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => null,
            'status' => 'active',
        ]);
        // Statut draft.
        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'draft',
        ]);
        // Hors fenêtre.
        OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'effective_from' => now()->addMonth()->toDateString(),
            'effective_to' => now()->addMonths(2)->toDateString(),
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $service->id, null, now()->toDateString());

        $this->assertNull($resolved);
    }

    public function test_picks_most_recent_when_several_apply(): void
    {
        $client = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $older = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'effective_from' => now()->subYear()->toDateString(),
            'allowed_service_catalog_ids' => [],
        ]);
        $newer = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'effective_from' => now()->subMonth()->toDateString(),
            'allowed_service_catalog_ids' => [],
        ]);

        $resolved = app(ContractResolver::class)
            ->resolveForBooking($client, $service->id, null, now()->toDateString());

        $this->assertSame($newer->id, $resolved->id);
    }
}
