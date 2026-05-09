<?php

namespace Tests\Feature;

use App\Models\EnterpriseBookingApproval;
use App\Models\OrganizationAccount;
use App\Models\Booking;
use App\Models\User;
use App\Services\Enterprise\EnterpriseBookingApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_booking_approval_full_workflow(): void
    {
        $organization = OrganizationAccount::factory()->create();

        $client = User::factory()->create([
            'role' => 'entreprise',
            'is_active' => true,
            'organization_account_id' => $organization->id,
        ]);

        $manager = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $finance = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'organization_account_id' => $organization->id,
            'status' => 'en_attente',
        ]);

        $service = app(EnterpriseBookingApprovalService::class);

        $approval = $service->createForBooking($rdv, $client, 'Demande B2B');

        $this->assertEquals('pending_manager', $approval->status);

        $approval = $service->approveManager($approval, $manager, 'OK manager');

        $this->assertEquals('pending_finance', $approval->status);
        $this->assertEquals($manager->id, $approval->manager_approved_by_user_id);

        $approval = $service->approveFinance($approval, $finance, 'OK finance');

        $this->assertEquals('approved', $approval->status);
        $this->assertEquals($finance->id, $approval->finance_approved_by_user_id);
        $this->assertEquals('confirme', $rdv->fresh()->status);
    }

    public function test_enterprise_approval_can_be_rejected(): void
    {
        $organization = OrganizationAccount::factory()->create();

        $client = User::factory()->create([
            'role' => 'entreprise',
            'is_active' => true,
            'organization_account_id' => $organization->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'organization_account_id' => $organization->id,
            'status' => 'en_attente',
        ]);

        $service = app(EnterpriseBookingApprovalService::class);

        $approval = $service->createForBooking($rdv, $client);
        $approval = $service->reject($approval, $admin, 'Budget refusé');

        $this->assertEquals('rejected', $approval->status);
        $this->assertEquals('refuse', $rdv->fresh()->status);
    }
}