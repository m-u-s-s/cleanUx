<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingTipFactory extends Factory
{
    protected $model = BookingTip::class;

    public function definition(): array
    {
        return [
            'code' => 'tip_' . Str::lower(Str::random(20)),
            'booking_id' => Booking::factory(),
            'client_user_id' => User::factory(),
            'provider_user_id' => User::factory(),
            'amount_cents' => fake()->numberBetween(200, 5000),
            'currency' => 'EUR',
            'status' => BookingTip::STATUS_PENDING,
            'message' => fake()->optional()->sentence(),
            'preset_label' => fake()->optional()->randomElement(['10%', '15%', '20%']),
            'preset_percent' => fake()->optional()->randomElement([10, 15, 20]),
            'metadata' => [],
        ];
    }
}
