<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantDomain>
 */
class TenantDomainFactory extends Factory
{
    protected $model = TenantDomain::class;

    public function definition(): array
    {
        return [
            'tenant_id'               => Tenant::factory(),
            'domain'                  => fake()->unique()->domainName(),
            'is_primary'              => false,
            'is_verified'             => true,
            'verified_at'             => now()->subDays(fake()->numberBetween(1, 30)),
            'ssl_status'              => TenantDomain::SSL_READY,
            'certificate_expires_at'  => now()->addYear(),
            'metadata'                => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }

    public function pending(): static
    {
        return $this->state([
            'is_verified' => false,
            'verified_at' => null,
            'ssl_status'  => TenantDomain::SSL_PENDING,
        ]);
    }
}
