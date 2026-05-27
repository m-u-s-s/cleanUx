<?php

namespace Database\Factories;

use App\Models\InspectionItem;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InspectionItemFactory extends Factory
{
    protected $model = InspectionItem::class;

    public function definition(): array
    {
        return [
            'inspection_id' => fn () => MissionQualityInspection::factory()->create()->id,
            'checklist_item_id' => fn () => QualityChecklistItem::factory()->create()->id,
            'value' => null,
            'photos_count' => 0,
            'comment' => null,
            'met' => true,
            'score_awarded' => 10,
            'recorded_by_user_id' => fn () => User::factory()->create()->id,
            'recorded_at' => now(),
            'metadata' => null,
        ];
    }
}
