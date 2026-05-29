<?php

namespace Database\Factories;

use App\Models\EnterpriseWorkOrder;
use App\Models\User;
use App\Models\WorkOrderApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderApprovalFactory extends Factory
{
    protected $model = WorkOrderApproval::class;

    public function definition(): array
    {
        return [
            'enterprise_work_order_id' => fn () => EnterpriseWorkOrder::factory()->create()->id,
            'approver_user_id' => fn () => User::factory()->create()->id,
            'approval_status' => 'pending',
            'approved_at' => null,
            'rejected_at' => null,
            'comment' => null,
            'metadata' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'approval_status' => 'rejected',
            'rejected_at' => now(),
            'comment' => 'Budget exceeded',
        ]);
    }
}
