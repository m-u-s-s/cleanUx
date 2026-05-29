<?php

namespace Database\Factories;

use App\Models\ProviderPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderPayoutFactory extends Factory
{
    protected $model = ProviderPayout::class;

    public function definition(): array
    {
        return [
            'provider_user_id' => User::factory()->employe(),
            'amount' => $this->faker->randomFloat(2, 20, 500),
            'currency' => 'eur',
            'status' => ProviderPayout::STATUS_PENDING,
            'provider' => 'stripe_connect',
            'provider_payout_id' => null,
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'metadata' => [],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => ProviderPayout::STATUS_PAID,
            'provider_payout_id' => 'po_test_'.$this->faker->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ProviderPayout::STATUS_FAILED,
            'metadata' => ['failure_code' => 'account_closed'],
        ]);
    }
}
