<?php

namespace Database\Factories;

use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessSanctionsCheckFactory extends Factory
{
    protected $model = BusinessSanctionsCheck::class;

    public function definition(): array
    {
        return [
            'entity_id' => fn () => BusinessEntity::factory()->create()->id,
            'list_name' => fake()->randomElement(['EU_SANCTIONS', 'UN_SANCTIONS', 'OFAC']),
            'status' => BusinessSanctionsCheck::STATUS_CLEAR,
            'match_count' => 0,
            'match_payload' => null,
            'provider' => 'mock',
            'checked_at' => now(),
            'expires_at' => now()->addDays(90),
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'reviewer_notes' => null,
            'metadata' => null,
        ];
    }

    public function match(): static
    {
        return $this->state(fn () => [
            'status' => BusinessSanctionsCheck::STATUS_MATCH,
            'match_count' => 1,
        ]);
    }
}
