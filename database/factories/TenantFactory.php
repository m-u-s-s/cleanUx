<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(4));

        return [
            'code'                  => 'T_' . Str::upper(Str::random(8)),
            'name'                  => $name,
            'slug'                  => $slug,
            'plan_code'             => fake()->randomElement(['basic', 'growth', 'enterprise']),
            'status'                => Tenant::STATUS_ACTIVE,
            'primary_domain'        => $slug . '.cleanux.app',
            'contact_email'         => fake()->companyEmail(),
            'billing_owner_user_id' => User::factory(),
            'default_locale'        => fake()->randomElement(['fr', 'nl', 'en']),
            'default_currency'      => 'EUR',
            'default_country_code'  => fake()->randomElement(['BE', 'FR', 'NL']),
            'settings'              => [],
            'theming'               => [],
            'features'              => [],
            'trial_ends_at'         => null,
            'activated_at'          => now()->subDays(30),
            'suspended_at'          => null,
            'suspended_reason'      => null,
            'archived_at'           => null,
            'metadata'              => [],
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'status'        => Tenant::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(14),
            'activated_at'  => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status'           => Tenant::STATUS_SUSPENDED,
            'suspended_at'     => now()->subDay(),
            'suspended_reason' => 'payment_failure',
        ]);
    }
}
