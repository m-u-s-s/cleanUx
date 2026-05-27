<?php

namespace Database\Factories;

use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KycVerificationFactory extends Factory
{
    protected $model = KycVerification::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->id,
            'provider' => fake()->randomElement(['mock', 'onfido', 'veriff', 'sumsub']),
            'external_applicant_id' => null,
            'external_check_id' => null,
            'status' => KycVerification::STATUS_PENDING,
            'decision' => KycVerification::DECISION_PENDING,
            'score' => null,
            'country_code' => 'BE',
            'requested_checks' => ['document', 'facial_similarity'],
            'result_summary' => null,
            'rejection_reason' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'started_at' => now(),
            'completed_at' => null,
            'expires_at' => now()->addYear(),
            'metadata' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => KycVerification::STATUS_CLEAR,
            'decision' => KycVerification::DECISION_APPROVED,
            'completed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => KycVerification::STATUS_REJECTED,
            'decision' => KycVerification::DECISION_REJECTED,
            'rejection_reason' => 'Document not valid',
            'completed_at' => now(),
        ]);
    }
}
