<?php

namespace Database\Factories;

use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionChecklistItemFactory extends Factory
{
    protected $model = MissionChecklistItem::class;

    public function definition(): array
    {
        return [
            'mission_checklist_id' => fn () => MissionChecklist::factory()->create()->id,
            'label' => fake()->sentence(4),
            'item_type' => fake()->randomElement(['checkbox', 'photo', 'text']),
            'is_required' => true,
            'status' => 'pending',
            'completed_by_user_id' => null,
            'completed_at' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
