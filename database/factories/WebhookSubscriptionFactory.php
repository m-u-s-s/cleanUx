<?php

namespace Database\Factories;

use App\Models\WebhookEndpoint;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        return [
            'endpoint_id' => fn () => WebhookEndpoint::factory()->create()->id,
            'event_code' => fake()->randomElement([
                'booking.created', 'booking.completed', 'payment.succeeded', 'user.registered',
            ]),
            'filters' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
