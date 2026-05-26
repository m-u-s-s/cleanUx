<?php

namespace Database\Factories;

use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsuranceClaimFactory extends Factory
{
    protected $model = InsuranceClaim::class;

    public function definition(): array
    {
        return [
            'booking_insurance_id' => BookingInsurance::factory(),
            'claimant_user_id' => User::factory(),
            'status' => 'filed',
            'incident_type' => fake()->randomElement(['damage', 'theft', 'injury', 'liability', 'other']),
            'incident_description' => fake()->paragraph(),
            'incident_date' => fake()->date(),
            'amount_claimed_cents' => fake()->numberBetween(5000, 100000),
            'filed_at' => now(),
            'evidence' => [],
            'idempotency_key' => fake()->uuid(),
            'metadata' => [],
        ];
    }
}
