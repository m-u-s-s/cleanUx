<?php

namespace Database\Factories;

use App\Models\AuditRetentionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuditRetentionPolicyFactory extends Factory
{
    protected $model = AuditRetentionPolicy::class;

    public function definition(): array
    {
        return [
            'code' => 'ret_'.Str::lower(Str::random(8)),
            'name' => fake()->words(3, true),
            'domain' => fake()->randomElement(['booking', 'payment', 'auth', 'admin']),
            'retention_days' => fake()->randomElement([30, 90, 180, 365]),
            'applies_to_severity' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
