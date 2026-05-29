<?php

namespace Database\Factories;

use App\Models\GdprDataRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GdprDataRequestFactory extends Factory
{
    protected $model = GdprDataRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['export', 'erasure']),
            'status' => 'pending',
            'reference' => 'GDPR-'.fake()->unique()->numerify('######'),
            'reason' => fake()->optional()->sentence(),
            'export_format' => 'json',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => [],
        ];
    }
}
