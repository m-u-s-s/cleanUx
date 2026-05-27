<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/' . Str::random(32);

        return [
            'user_id'         => fn () => User::factory()->create()->id,
            'endpoint'        => $endpoint,
            'endpoint_hash'   => PushSubscription::hashEndpoint($endpoint),
            'p256dh'          => base64_encode(random_bytes(32)),
            'auth'            => base64_encode(random_bytes(16)),
            'user_agent'      => 'Mozilla/5.0',
            'platform'        => 'web',
            'browser'         => 'Chrome',
            'is_active'       => true,
            'failure_count'   => 0,
            'last_failure_at' => null,
            'last_used_at'    => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active'     => false,
            'failure_count' => 5,
        ]);
    }
}
