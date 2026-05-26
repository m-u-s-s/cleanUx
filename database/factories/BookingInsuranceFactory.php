<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\InsurancePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingInsuranceFactory extends Factory
{
    protected $model = BookingInsurance::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'plan_id' => InsurancePlan::factory(),
            'user_id' => User::factory(),
            'policy_number' => 'POL-' . Str::upper(Str::random(10)),
            'premium_cents' => fake()->numberBetween(200, 2000),
            'coverage_amount_cents' => fake()->randomElement([50000, 100000]),
            'currency' => 'EUR',
            'status' => 'active',
            'external_provider' => 'mock',
            'purchased_at' => now(),
            'effective_from' => now(),
            'effective_until' => now()->addDays(30),
            'idempotency_key' => fake()->uuid(),
            'metadata' => [],
        ];
    }
}
