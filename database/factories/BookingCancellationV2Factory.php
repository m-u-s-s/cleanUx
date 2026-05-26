<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingCancellationV2Factory extends Factory
{
    protected $model = BookingCancellationV2::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'cancelled_by_user_id' => User::factory(),
            'actor_role' => fake()->randomElement(['client', 'provider', 'admin']),
            'reason_code' => fake()->randomElement(['changed_mind', 'schedule_conflict', 'provider_unavailable', 'emergency']),
            'reason_text' => fake()->optional()->sentence(),
            'fee_percent_applied' => fake()->randomFloat(2, 0, 100),
            'fee_amount_cents' => fake()->numberBetween(0, 5000),
            'refund_amount_cents' => fake()->numberBetween(0, 20000),
            'currency' => 'EUR',
            'refund_method' => fake()->randomElement(['stripe', 'credit', 'promo']),
            'exempt_applied' => false,
            'booking_status_before' => 'confirme',
            'booking_status_after' => 'annule',
            'integrations_log' => [],
            'idempotency_key' => fake()->uuid(),
            'cancelled_at' => now(),
            'metadata' => [],
        ];
    }
}
