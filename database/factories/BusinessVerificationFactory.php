<?php

namespace Database\Factories;

use App\Models\BusinessEntity;
use App\Models\BusinessVerification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BusinessVerificationFactory extends Factory
{
    protected $model = BusinessVerification::class;

    public function definition(): array
    {
        return [
            'entity_id' => fn () => BusinessEntity::factory()->create()->id,
            'provider' => fake()->randomElement(['mock', 'insee', 'vies']),
            'check_type' => fake()->randomElement(['vat', 'company_registry', 'address']),
            'status' => BusinessVerification::STATUS_SUCCESS,
            'idempotency_key' => (string) Str::uuid(),
            'request_payload' => [],
            'response_payload' => [],
            'matched_value' => null,
            'checked_at' => now(),
            'expires_at' => now()->addDays(90),
            'last_error' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => BusinessVerification::STATUS_FAILED,
            'last_error' => 'Verification failed',
        ]);
    }
}
