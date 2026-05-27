<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMember>
 */
class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'user_id'                 => User::factory(),
            'role'                    => fake()->randomElement([
                OrganizationRole::WORKER->value,
                OrganizationRole::MANAGER->value,
                OrganizationRole::VIEWER->value,
            ]),
            'permissions' => null,
            'status'      => 'active',
            'invited_by'  => null,
            'invited_at'  => now()->subDays(fake()->numberBetween(1, 60)),
            'joined_at'   => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => OrganizationRole::OWNER->value]);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'joined_at' => null]);
    }
}
