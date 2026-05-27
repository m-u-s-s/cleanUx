<?php

namespace Database\Factories;

use App\Models\CancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CancellationPolicyFactory extends Factory
{
    protected $model = CancellationPolicy::class;

    public function definition(): array
    {
        return [
            'code'        => 'policy_' . Str::upper(Str::random(6)),
            'name'        => fake()->words(3, true) . ' policy',
            'description' => fake()->sentence(),
            'trade_codes' => fake()->optional()->randomElements(['cleaning', 'painting', 'babysitting'], 2),
            'actor_role'  => fake()->randomElement([
                CancellationPolicy::ACTOR_CLIENT,
                CancellationPolicy::ACTOR_PROVIDER,
                CancellationPolicy::ACTOR_BOTH,
            ]),
            'is_active'   => true,
            'version'     => 1,
            'valid_from'  => now()->subMonth()->toDateString(),
            'valid_until' => null,
            'metadata'    => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
