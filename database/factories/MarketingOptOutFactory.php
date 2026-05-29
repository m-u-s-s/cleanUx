<?php

namespace Database\Factories;

use App\Models\MarketingOptOut;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingOptOutFactory extends Factory
{
    protected $model = MarketingOptOut::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => fake()->randomElement([
                MarketingOptOut::CHANNEL_EMAIL,
                MarketingOptOut::CHANNEL_SMS,
                MarketingOptOut::CHANNEL_PUSH,
                MarketingOptOut::CHANNEL_ALL,
            ]),
            'opted_out_at' => now()->subDays(fake()->numberBetween(1, 90)),
            'reason' => fake()->optional()->randomElement(['unsubscribe_link', 'user_request', 'admin']),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }
}
