<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => fake()->randomElement(['email', 'sms', 'push', 'in_app', 'whatsapp']),
            'category' => fake()->randomElement(['booking_updates', 'marketing', 'security', 'reminders', 'tips', 'loyalty', 'disputes']),
            'is_allowed' => true,
            'version' => 1,
            'source' => 'default',
            'metadata' => [],
        ];
    }
}
