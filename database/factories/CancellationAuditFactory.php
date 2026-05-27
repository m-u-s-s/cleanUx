<?php

namespace Database\Factories;

use App\Models\BookingCancellationV2;
use App\Models\CancellationAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CancellationAuditFactory extends Factory
{
    protected $model = CancellationAudit::class;

    public function definition(): array
    {
        return [
            'cancellation_id' => fn () => BookingCancellationV2::factory()->create()->id,
            'actor_user_id' => fn () => User::factory()->create()->id,
            'action' => CancellationAudit::ACTION_CREATED,
            'before_state' => null,
            'after_state' => null,
            'notes' => null,
            'occurred_at' => now(),
        ];
    }
}
