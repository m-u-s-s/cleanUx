<?php

namespace Database\Factories;

use App\Models\KycCheck;
use App\Models\KycVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

class KycCheckFactory extends Factory
{
    protected $model = KycCheck::class;

    public function definition(): array
    {
        return [
            'kyc_verification_id' => fn () => KycVerification::factory()->create()->id,
            'check_type' => fake()->randomElement([
                KycCheck::TYPE_DOCUMENT,
                KycCheck::TYPE_FACIAL_SIMILARITY,
                KycCheck::TYPE_WATCHLIST_AML,
            ]),
            'result' => KycCheck::RESULT_PENDING,
            'sub_result' => null,
            'external_id' => null,
            'confidence' => null,
            'breakdown' => null,
            'notes' => null,
            'checked_at' => null,
        ];
    }

    public function clear(): static
    {
        return $this->state(fn () => [
            'result' => KycCheck::RESULT_CLEAR,
            'confidence' => '0.98',
            'checked_at' => now(),
        ]);
    }
}
