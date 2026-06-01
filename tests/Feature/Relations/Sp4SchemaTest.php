<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\ContractRateCard;
use App\Models\ContractSlaEvent;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sp4SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_has_provider_organization_and_rate_cards(): void
    {
        $client = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $client->id,
            'provider_organization_id' => $provider->id,
        ]);

        $card = ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1800,
            'currency' => 'EUR',
        ]);

        $this->assertSame($provider->id, $contract->fresh()->providerOrganization->id);
        $this->assertTrue($contract->rateCards->contains('id', $card->id));
    }

    public function test_booking_and_mission_carry_contract_and_sla_columns(): void
    {
        $contract = OrganizationContract::factory()->create();

        $booking = Booking::factory()->create(['organization_contract_id' => $contract->id]);
        $this->assertSame($contract->id, $booking->fresh()->organization_contract_id);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'organization_contract_id' => $contract->id,
            'sla_response_due_at' => now()->addHours(4),
            'sla_resolution_due_at' => now()->addHours(24),
            'planned_start_at' => now(),
        ]);

        $event = ContractSlaEvent::create([
            'mission_id' => $mission->id,
            'organization_contract_id' => $contract->id,
            'kind' => 'response',
            'due_at' => now()->addHours(4),
            'status' => 'pending',
        ]);

        $this->assertNotNull($mission->fresh()->sla_response_due_at);
        $this->assertSame('pending', $event->fresh()->status);
        $this->assertSame($mission->id, $event->mission->id);
    }
}
