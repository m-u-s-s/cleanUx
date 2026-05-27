<?php

namespace Database\Factories;

use App\Models\InspectionPhoto;
use App\Models\MissionQualityInspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InspectionPhotoFactory extends Factory
{
    protected $model = InspectionPhoto::class;

    public function definition(): array
    {
        return [
            'inspection_id' => fn () => MissionQualityInspection::factory()->create()->id,
            'inspection_item_id' => null,
            'photo_path' => 'photos/inspection/' . fake()->uuid() . '.jpg',
            'photo_type' => InspectionPhoto::TYPE_AFTER,
            'uploaded_by_user_id' => fn () => User::factory()->create()->id,
            'uploaded_at' => now(),
            'ip_hash' => null,
            'metadata' => null,
        ];
    }

    public function before(): static
    {
        return $this->state(fn () => ['photo_type' => InspectionPhoto::TYPE_BEFORE]);
    }
}
