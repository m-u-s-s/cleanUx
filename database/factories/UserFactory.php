<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Enums\OrganizationType;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            'current_team_id' => null,
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
            'organization_account_id' => null,
            'postal_code_id' => null,
            'primary_service_zone_id' => null,
            'phone' => null,
            'locale' => 'fr_BE',
            'timezone' => 'Europe/Brussels',
            'status' => 'active',
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** UN ADMINISTRATEUR QUI PEUT REELLEMENT TOUT FAIRE, sans etre super-administrateur. */
    public function adminComplet(): static
    {
        return $this->admin()->state(fn (array $attributes) => [
            'permissions' => array_keys(User::allowedAdminPermissions()),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'platform_role' => 'admin',
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);
    }

    /** Client personal — creates the customer_profiles row separately if needed. */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);
    }

    public function premiumClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_started_at' => now()->subMonth(),
            'premium_renewal_at' => now()->addMonth(),
        ]);
    }

    /** Provider / employe — la colonne `role` existe toujours et cet état la pose. */
    public function employe(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'employe',
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);
    }

    /** Company client — la colonne `role` existe toujours et cet état la pose. */
    public function entreprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'entreprise',
            'tva_number' => fake()->unique()->numerify('BE0#########'),
            'duree_creneau' => 90,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);
    }

    /** UNE SOCIÉTÉ CLIENTE COMPLÈTE — et « complète » n'est pas un luxe. */
    public function societeCliente(?OrganizationAccount $organisation = null, string $roleOrganisation = 'owner'): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
        ])->afterCreating(function (User $utilisateur) use ($organisation, $roleOrganisation) {
            $org = $organisation ?? OrganizationAccount::factory()->create([
                'type' => OrganizationType::CLIENT_COMPANY->value,
            ]);

            $utilisateur->forceFill([
                'organization_account_id' => $org->id,
                'current_organization_id' => $org->id,
            ])->save();

            CustomerProfile::updateOrCreate(
                ['user_id' => $utilisateur->id],
                ['customer_type' => CustomerType::COMPANY->value],
            );

            OrganizationMember::updateOrCreate(
                ['organization_account_id' => $org->id, 'user_id' => $utilisateur->id],
                ['role' => $roleOrganisation, 'status' => 'active', 'joined_at' => now()],
            );

            $utilisateur->refresh();
        });
    }

    public function withPersonalTeam(?callable $callback = null): static
    {
        $teamClass = '\\App\\Models\\Team';

        if (! Features::hasTeamFeatures() || ! class_exists($teamClass) || ! method_exists($teamClass, 'factory')) {
            return $this->state([]);
        }

        return $this->has(
            $teamClass::factory()
                ->state(fn (array $attributes, User $user) => [
                    'name' => $user->name.'\'s Team',
                    'user_id' => $user->id,
                    'personal_team' => true,
                ])
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }
}
