<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantUserFactory extends Factory
{
    protected $model = TenantUser::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => Tenant::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'role' => TenantUser::ROLE_MEMBER,
            'joined_at' => now(),
            'left_at' => null,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => TenantUser::ROLE_OWNER]);
    }

    public function left(): static
    {
        return $this->state(fn () => [
            'left_at' => now(),
            'is_active' => false,
        ]);
    }
}
