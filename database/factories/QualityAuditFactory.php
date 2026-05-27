<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\QualityAudit;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityAuditFactory extends Factory
{
    protected $model = QualityAudit::class;

    public function definition(): array
    {
        return [
            'rendez_vous_id' => fn () => Booking::factory()->create()->id,
            'employe_id' => fn () => User::factory()->employe()->create()->id,
            'service_zone_id' => fn () => ServiceZone::factory()->create()->id,
            'auditor_id' => fn () => User::factory()->admin()->create()->id,
            'score' => fake()->numberBetween(0, 100),
            'punctuality_score' => fake()->numberBetween(0, 100),
            'service_score' => fake()->numberBetween(0, 100),
            'communication_score' => fake()->numberBetween(0, 100),
            'checklist' => [],
            'attachment_evidence' => [],
            'notes' => fake()->optional()->paragraph(),
            'action_plan' => null,
            'follow_up_required' => false,
            'follow_up_due_at' => null,
            'status' => 'open',
            'audited_at' => now(),
            'closed_at' => null,
        ];
    }
}
