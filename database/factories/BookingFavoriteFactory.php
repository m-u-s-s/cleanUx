<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFavoriteFactory extends Factory
{
    protected $model = BookingFavorite::class;

    public function definition(): array
    {
        return [
            'client_user_id' => User::factory(),
            'label' => fake()->sentence(3),
            'source_booking_id' => Booking::factory(),
            'preferred_provider_user_id' => User::factory(),
            'snapshot' => ['address' => fake()->address(), 'service' => fake()->word()],
            'use_count' => fake()->numberBetween(0, 10),
        ];
    }
}
