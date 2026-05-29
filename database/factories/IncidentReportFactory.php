<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentReport>
 */
class IncidentReportFactory extends Factory
{
    protected $model = IncidentReport::class;

    public function definition(): array
    {
        return [
            'rendez_vous_id' => Booking::factory(),
            'employe_id' => User::factory()->employe(),
            'client_id' => User::factory()->client(),
            'organization_account_id' => null,
            'assigned_to' => null,
            'type' => fake()->randomElement(['damage', 'accident', 'quality', 'safety', 'other']),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'sla_policy' => 'standard',
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'open',
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'location_notes' => fake()->optional()->sentence(),
            'photos' => null,
            'attachments' => null,
            'meta' => null,
            'first_response_at' => null,
            'due_at' => now()->addHours(24),
            'resolution_notes' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => 'resolved',
            'resolution_notes' => fake()->paragraph(),
            'resolved_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }
}
