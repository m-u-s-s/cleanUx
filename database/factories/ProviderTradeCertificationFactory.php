<?php

namespace Database\Factories;

use App\Models\ProviderTradeCertification;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderTradeCertification>
 */
class ProviderTradeCertificationFactory extends Factory
{
    protected $model = ProviderTradeCertification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trade_id' => Trade::factory(),
            'certification_type' => $this->faker->randomElement(['diploma', 'attestation', 'experience']),
            'document_path' => $this->faker->boolean(60)
                ? 'certifications/'.$this->faker->uuid().'.pdf'
                : null,
            'status' => 'pending',
            'verified_by_user_id' => null,
            'verified_at' => null,
            'expires_at' => $this->faker->boolean(40) ? $this->faker->dateTimeBetween('+6 months', '+3 years') : null,
            'metadata' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => 'verified',
            'verified_by_user_id' => User::factory(),
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    public function diploma(): static
    {
        return $this->state(fn () => ['certification_type' => 'diploma']);
    }

    public function attestation(): static
    {
        return $this->state(fn () => ['certification_type' => 'attestation']);
    }
}
