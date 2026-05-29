<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionChecklist>
 */
class MissionChecklistFactory extends Factory
{
    protected $model = MissionChecklist::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'service_catalog_id' => ServiceCatalog::factory(),
            'template_name' => fake()->randomElement([
                'Checklist Nettoyage Standard',
                'Checklist Peinture Intérieure',
                'Checklist Jardin',
                'Checklist Bureaux',
            ]),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed']),
            'completion_rate' => fake()->randomFloat(2, 0, 100),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'completion_rate' => 100.0,
        ]);
    }
}
