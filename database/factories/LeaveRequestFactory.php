<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'user_id' => User::factory(),
            'type' => 'paid',
            'starts_on' => now()->addDays(3)->toDateString(),
            'ends_on' => now()->addDays(7)->toDateString(),
            'status' => LeaveRequest::STATUS_PENDING,
        ];
    }

    public function approuvee(): static
    {
        return $this->state(fn () => [
            'status' => LeaveRequest::STATUS_APPROVED,
            'decided_at' => now(),
        ]);
    }
}
