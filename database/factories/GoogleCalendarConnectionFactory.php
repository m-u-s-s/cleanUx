<?php

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoogleCalendarConnectionFactory extends Factory
{
    protected $model = GoogleCalendarConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->id,
            'google_email' => fake()->safeEmail(),
            'google_user_id' => fake()->numerify('####################'),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'sync_enabled' => true,
            'last_synced_at' => null,
            'last_sync_status' => null,
            'last_sync_error' => null,
            'meta' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->subHour(),
        ]);
    }
}
