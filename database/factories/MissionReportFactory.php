<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionReport>
 */
class MissionReportFactory extends Factory
{
    protected $model = MissionReport::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'generated_by_user_id' => User::factory(),
            'report_number' => 'RPT-'.strtoupper(fake()->unique()->bothify('######')),
            'status' => fake()->randomElement(['draft', 'generated', 'sent', 'validated']),
            'generated_at' => now()->subHours(fake()->numberBetween(0, 24)),
            'summary' => fake()->paragraph(),
            'checklist_completion_rate' => fake()->randomFloat(2, 0.5, 1.0),
            'before_photos_count' => fake()->numberBetween(0, 5),
            'after_photos_count' => fake()->numberBetween(0, 5),
            'incident_count' => 0,
            'client_validation' => null,
            'quality_score' => fake()->randomFloat(2, 60, 100),
            'report_payload' => null,
            'pdf_path' => null,
        ];
    }

    public function validated(): static
    {
        return $this->state([
            'status' => 'validated',
            'client_validation' => 'approved',
        ]);
    }
}
