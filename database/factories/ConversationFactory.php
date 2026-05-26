<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'rendez_vous_id' => Booking::factory(),
            'mission_id' => Mission::factory(),
            'type' => fake()->randomElement(['client_provider', 'admin_client', 'admin_provider']),
            'status' => fake()->randomElement(['open', 'closed']),
        ];
    }
}
