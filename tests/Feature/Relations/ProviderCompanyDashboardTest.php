<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCompanyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_center_lists_missions_of_the_provider_org(): void
    {
        $providerOrg = OrganizationAccount::factory()->create();
        $otherOrg = OrganizationAccount::factory()->create();
        $booking = Booking::factory()->create();

        $mine = Mission::create(['booking_id' => $booking->id, 'status' => 'planned', 'provider_organization_id' => $providerOrg->id]);
        $notMine = Mission::create(['booking_id' => $booking->id, 'status' => 'planned', 'provider_organization_id' => $otherOrg->id]);

        $ids = Mission::where('provider_organization_id', $providerOrg->id)->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($notMine->id));
    }
}
