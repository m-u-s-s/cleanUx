<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationInvitation> */
class OrganizationInvitationFactory extends Factory
{
    protected $model = OrganizationInvitation::class;

    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => OrganizationRole::WORKER->value,
            'invited_by' => User::factory(),
            'token' => OrganizationInvitation::genererJeton(),
            'status' => 'pending',
            'expires_at' => now()->addDays(14),
        ];
    }

    /** Une invitation dont le délai est écoulé : le jeton ne doit plus rien ouvrir. */
    public function expiree(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function acceptee(): static
    {
        return $this->state(fn () => ['status' => 'accepted', 'accepted_at' => now()]);
    }
}
