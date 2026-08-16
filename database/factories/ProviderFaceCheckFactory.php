<?php

namespace Database\Factories;

use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderFaceCheck>
 */
class ProviderFaceCheckFactory extends Factory
{
    protected $model = ProviderFaceCheck::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider_face_profile_id' => ProviderFaceProfile::factory(),
            'triggered_by' => ProviderFaceCheck::TRIGGER_INTERVAL,
            'status' => ProviderFaceCheck::STATUS_PENDING,
            'attempt_number' => 1,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function passed(): self
    {
        return $this->state(fn () => [
            'status' => ProviderFaceCheck::STATUS_PASSED,
            'decision_source' => ProviderFaceCheck::SOURCE_AUTO,
            'score' => 91.5,
            'liveness_result' => ProviderFaceCheck::LIVENESS_PASS,
            'answered_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => ProviderFaceCheck::STATUS_FAILED,
            'decision_source' => ProviderFaceCheck::SOURCE_AUTO,
            'score' => 21.0,
            'liveness_result' => ProviderFaceCheck::LIVENESS_PASS,
            'failure_reason' => 'score_below_threshold',
            'answered_at' => now(),
        ]);
    }

    public function abandoned(): self
    {
        return $this->state(fn () => [
            'status' => ProviderFaceCheck::STATUS_ABANDONED,
            'failure_reason' => 'abandoned_by_provider',
        ]);
    }
}
