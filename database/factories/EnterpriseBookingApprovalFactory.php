<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\EnterpriseBookingApproval;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnterpriseBookingApprovalFactory extends Factory
{
    protected $model = EnterpriseBookingApproval::class;

    public function definition(): array
    {
        return [
            'rendez_vous_id'                => fn () => Booking::factory()->create()->id,
            'organization_account_id'       => fn () => OrganizationAccount::factory()->create()->id,
            'organization_site_id'          => null,
            'requested_by_user_id'          => fn () => User::factory()->create()->id,
            'manager_approved_by_user_id'   => null,
            'finance_approved_by_user_id'   => null,
            'status'                        => 'pending_manager',
            'request_note'                  => fake()->sentence(),
            'manager_note'                  => null,
            'finance_note'                  => null,
            'rejection_reason'              => null,
            'manager_approved_at'           => null,
            'finance_approved_at'           => null,
            'approved_at'                   => null,
            'rejected_at'                   => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status'      => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status'           => 'rejected',
            'rejection_reason' => 'Budget exceeded',
            'rejected_at'      => now(),
        ]);
    }
}
