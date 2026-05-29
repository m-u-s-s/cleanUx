<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'event_name' => fake()->randomElement(['page_view', 'booking_started', 'booking_completed', 'signup', 'search']),
            'event_category' => fake()->randomElement(['lifecycle', 'funnel', 'engagement', 'transaction', 'error']),
            'session_id' => fake()->uuid(),
            'user_id' => User::factory(),
            'properties' => ['page' => '/'.fake()->slug()],
            'source' => fake()->randomElement(['web', 'mobile', 'api']),
            'platform' => fake()->randomElement(['ios', 'android', 'web']),
            'locale' => fake()->randomElement(['fr', 'nl', 'en']),
            'country_code' => fake()->randomElement(['BE', 'FR', 'NL']),
            'idempotency_key' => fake()->uuid(),
            'occurred_at' => now(),
        ];
    }
}
