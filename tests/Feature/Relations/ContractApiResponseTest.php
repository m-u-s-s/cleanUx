<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractApiResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_show_exposes_contract_coverage(): void
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'contract_reference' => 'CT-API-1',
            'status' => 'active',
        ]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'organization_contract_id' => $contract->id,
        ]);

        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.contract_covered', true)
            ->assertJsonPath('data.contract_label', 'CT-API-1');
    }

    public function test_booking_show_without_contract_reports_no_coverage(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'organization_contract_id' => null,
        ]);

        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.contract_covered', false)
            ->assertJsonPath('data.contract_label', null);
    }
}
