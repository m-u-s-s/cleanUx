<?php

namespace Database\Factories;

use App\Models\EmployeeZoneAssignment;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeZoneAssignment>
 */
class EmployeeZoneAssignmentFactory extends Factory
{
    protected $model = EmployeeZoneAssignment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employe(),
            'service_zone_id' => ServiceZone::factory(),
            'assignment_type' => fake()->randomElement(['primary', 'secondary', 'backup']),
            'coverage_priority' => fake()->numberBetween(1, 500),
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
