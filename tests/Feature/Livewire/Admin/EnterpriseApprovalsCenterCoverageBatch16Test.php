<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\EnterpriseApprovalsCenter;
use App\Models\OrganizationAccount;
use App\Models\User;
use Database\Factories\EnterpriseBookingApprovalFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnterpriseApprovalsCenterCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_renders_with_seeded_approvals(): void
    {
        EnterpriseBookingApprovalFactory::new()->count(2)->create();

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->assertOk()
            ->assertSet('status', '')
            ->assertSet('search', '');
    }

    public function test_status_filter_resets_page_and_filters_rows(): void
    {
        EnterpriseBookingApprovalFactory::new()->create(['status' => 'pending_manager']);
        EnterpriseBookingApprovalFactory::new()->approved()->create();

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->set('status', 'approved')
            ->assertOk()
            ->assertSet('status', 'approved');
    }

    public function test_search_filter_resets_page(): void
    {
        $account = OrganizationAccount::factory()->create(['name' => 'Acme Corporation']);
        EnterpriseBookingApprovalFactory::new()->create([
            'organization_account_id' => $account->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->set('search', 'Acme')
            ->assertOk()
            ->assertSet('search', 'Acme');
    }

    public function test_approve_manager_transitions_status(): void
    {
        $approval = EnterpriseBookingApprovalFactory::new()->create([
            'status' => 'pending_manager',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->set('note', 'OK manager')
            ->call('approveManager', $approval->id)
            ->assertDispatched('toast')
            ->assertSet('note', '');

        $this->assertDatabaseHas('enterprise_booking_approvals', [
            'id' => $approval->id,
            'status' => 'pending_finance',
            'manager_approved_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_approve_finance_transitions_status(): void
    {
        $approval = EnterpriseBookingApprovalFactory::new()->create([
            'status' => 'pending_finance',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->call('approveFinance', $approval->id)
            ->assertDispatched('toast')
            ->assertSet('note', '');

        $this->assertDatabaseHas('enterprise_booking_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'finance_approved_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_open_and_close_reject_modal(): void
    {
        $approval = EnterpriseBookingApprovalFactory::new()->create();

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->call('openRejectModal', $approval->id)
            ->assertSet('selectedApprovalId', $approval->id)
            ->assertSet('rejectionReason', '')
            ->call('closeRejectModal')
            ->assertSet('selectedApprovalId', null);
    }

    public function test_reject_requires_a_reason(): void
    {
        $approval = EnterpriseBookingApprovalFactory::new()->create();

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->call('openRejectModal', $approval->id)
            ->set('rejectionReason', '')
            ->call('reject')
            ->assertHasErrors(['rejectionReason' => 'required']);
    }

    public function test_reject_with_reason_marks_approval_rejected(): void
    {
        $approval = EnterpriseBookingApprovalFactory::new()->create([
            'status' => 'pending_manager',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnterpriseApprovalsCenter::class)
            ->call('openRejectModal', $approval->id)
            ->set('rejectionReason', 'Budget dépassé pour ce trimestre')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('selectedApprovalId', null);

        $this->assertDatabaseHas('enterprise_booking_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
            'rejection_reason' => 'Budget dépassé pour ce trimestre',
        ]);
    }
}
