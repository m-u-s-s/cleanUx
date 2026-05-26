<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionQualityInspectionFactory extends Factory
{
    protected $model = MissionQualityInspection::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'booking_id' => Booking::factory(),
            'checklist_id' => QualityChecklist::factory(),
            'phase' => fake()->randomElement(['pre', 'during', 'post']),
            'status' => 'draft',
            'submitted_by_user_id' => User::factory(),
            'score_max' => 100,
            'idempotency_key' => fake()->uuid(),
            'metadata' => [],
        ];
    }
}
