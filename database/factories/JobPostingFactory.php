<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\OrganizationAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'reference' => JobPosting::genererUneReference(),
            'title' => 'Agent d’entretien (H/F)',
            'employment_type' => 'full_time',
            'status' => JobPosting::STATUS_DRAFT,
        ];
    }

    public function publiee(): static
    {
        return $this->state(fn () => [
            'status' => JobPosting::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
