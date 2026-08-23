<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\ContractSlaEvent;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Services\Missions\MissionFromRendezVousSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le chemin WEB/général de création de mission passe par MissionFromRendezVousSyncService (et non par CreateBookingFromApiAction / Work Orders). */
class WebBookingSlaTest extends TestCase
{
    use RefreshDatabase;

    private function contract(int $responseHours = 4, int $resolutionHours = 24): OrganizationContract
    {
        return OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'sla_response_hours' => $responseHours,
            'sla_resolution_hours' => $resolutionHours,
        ]);
    }

    public function test_web_booking_under_contract_carries_contract_and_arms_sla(): void
    {
        $contract = $this->contract();

        $booking = Booking::factory()->create([
            'organization_contract_id' => $contract->id,
        ]);

        $mission = app(MissionFromRendezVousSyncService::class)->createFromRendezVous($booking);

        $this->assertSame($contract->id, $mission->organization_contract_id);
        $this->assertNotNull($mission->sla_response_due_at);
        $this->assertNotNull($mission->sla_resolution_due_at);
        $this->assertSame(
            2,
            ContractSlaEvent::where('mission_id', $mission->id)->where('status', 'pending')->count(),
        );
    }

    public function test_web_booking_without_contract_is_a_strict_no_op(): void
    {
        $booking = Booking::factory()->create([
            'organization_contract_id' => null,
        ]);

        $mission = app(MissionFromRendezVousSyncService::class)->createFromRendezVous($booking);

        $this->assertNull($mission->organization_contract_id);
        $this->assertSame(
            0,
            ContractSlaEvent::where('mission_id', $mission->id)->count(),
        );
    }
}
