<?php

namespace Database\Factories;

use App\Models\MultiTradeBundle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MultiTradeBundleFactory extends Factory
{
    protected $model = MultiTradeBundle::class;

    public function definition(): array
    {
        return [
            'code'                    => MultiTradeBundle::generateCode(),
            'name'                    => fake()->words(3, true),
            'description'             => fake()->sentence(),
            'client_user_id'          => fn () => User::factory()->create()->id,
            'status'                  => MultiTradeBundle::STATUS_DRAFT,
            'total_estimated_cents'   => fake()->numberBetween(5000, 100000),
            'total_quoted_cents'      => null,
            'total_final_cents'       => null,
            'currency'                => 'EUR',
            'bundle_discount_percent' => 0,
            'bundle_discount_cents'   => 0,
            'address'                 => ['street' => fake()->streetAddress(), 'city' => 'Bruxelles', 'postal_code' => '1000'],
            'preferred_start_date'    => now()->addWeek()->toDateString(),
            'preferred_end_date'      => now()->addWeeks(3)->toDateString(),
            'metadata'                => null,
            'accepted_at'             => null,
            'completed_at'            => null,
            'cancelled_at'            => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status'      => MultiTradeBundle::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }
}
