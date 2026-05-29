<?php

namespace Database\Factories;

use App\Models\ClientSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientSubscriptionFactory extends Factory
{
    protected $model = ClientSubscription::class;

    public function definition(): array
    {
        return [
            'client_id' => fn () => User::factory()->create()->id,
            'plan_id' => fn () => SubscriptionPlan::factory()->create()->id,
            'service_zone_id' => null,
            'service_catalog_id' => null,
            'day_of_week' => fake()->numberBetween(1, 5),
            'heure' => '09:00',
            'preferred_employee_id' => null,
            'base_price' => fake()->randomFloat(2, 50, 200),
            'discounted_price' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'status' => 'active',
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'end_date' => now()->toDateString(),
        ]);
    }
}
