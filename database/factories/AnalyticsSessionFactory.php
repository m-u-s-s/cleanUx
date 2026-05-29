<?php

namespace Database\Factories;

use App\Models\AnalyticsSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnalyticsSessionFactory extends Factory
{
    protected $model = AnalyticsSession::class;

    public function definition(): array
    {
        return [
            'session_id' => (string) Str::uuid(),
            'user_id' => null,
            'anonymous_id' => (string) Str::uuid(),
            'source' => fake()->randomElement(['web', 'mobile', 'api']),
            'platform' => fake()->randomElement(['web', 'ios', 'android']),
            'locale' => fake()->randomElement(['fr_BE', 'nl_BE', 'en']),
            'country_code' => 'BE',
            'first_url' => fake()->url(),
            'first_referrer' => null,
            'user_agent_short' => 'Mozilla/5.0',
            'page_count' => fake()->numberBetween(1, 20),
            'event_count' => fake()->numberBetween(1, 50),
            'started_at' => now()->subMinutes(30),
            'last_seen_at' => now()->subMinutes(5),
            'ended_at' => null,
            'metadata' => null,
        ];
    }
}
