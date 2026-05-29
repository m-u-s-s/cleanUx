<?php

namespace Database\Factories;

use App\Models\QualityChecklist;
use App\Models\QualityChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QualityChecklistItem>
 */
class QualityChecklistItemFactory extends Factory
{
    protected $model = QualityChecklistItem::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            QualityChecklistItem::TYPE_BOOLEAN,
            QualityChecklistItem::TYPE_RATING,
            QualityChecklistItem::TYPE_TEXT,
            QualityChecklistItem::TYPE_PHOTO,
        ]);

        return [
            'checklist_id' => QualityChecklist::factory(),
            'position' => fake()->numberBetween(1, 20),
            'code' => Str::slug(fake()->unique()->words(3, true)),
            'label' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'item_type' => $type,
            'required' => fake()->boolean(70),
            'weight' => fake()->randomElement([1, 2, 3, 5]),
            'valid_options' => $type === QualityChecklistItem::TYPE_RATING ? [1, 2, 3, 4, 5] : null,
            'expected_value' => null,
            'metadata' => null,
        ];
    }

    public function boolean(): static
    {
        return $this->state(['item_type' => QualityChecklistItem::TYPE_BOOLEAN]);
    }

    public function rating(): static
    {
        return $this->state([
            'item_type' => QualityChecklistItem::TYPE_RATING,
            'valid_options' => [1, 2, 3, 4, 5],
        ]);
    }
}
