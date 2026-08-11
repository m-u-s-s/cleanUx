<?php

namespace Database\Factories;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'status' => JobApplication::STATUS_RECEIVED,
        ];
    }
}
