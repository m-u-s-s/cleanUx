<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationRolePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationRolePermission>
 *
 * `granted` est un booléen EXPLICITE, non une simple présence : une société peut aussi bien
 * retirer un droit accordé par défaut qu'en ajouter un.
 */
class OrganizationRolePermissionFactory extends Factory
{
    protected $model = OrganizationRolePermission::class;

    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'role' => OrganizationRole::TEAM_LEAD->value,
            'permission' => fake()->randomElement([
                'members.invite', 'tasks.create', 'team.create', 'missions.dispatch',
            ]),
            'granted' => true,
        ];
    }

    /** Le cas inverse : la société RETIRE à ce rôle un droit qu'il aurait par défaut. */
    public function retiree(): static
    {
        return $this->state(fn () => ['granted' => false]);
    }
}
