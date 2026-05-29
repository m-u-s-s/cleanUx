<?php

namespace Database\Factories;

use App\Models\AvailabilityHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AvailabilityHoldFactory extends Factory
{
    protected $model = AvailabilityHold::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 hour', '+24 hours');
        $end = (clone $start)->modify('+2 hours');

        return [
            'provider_user_id' => fn () => User::factory()->employe()->create()->id,
            'booking_id' => null,
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => 'booking',
            'expires_at' => now()->addHours(2),
            'released_at' => null,
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => ['released_at' => now()]);
    }
}
