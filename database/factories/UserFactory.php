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

    /**
     * UN ADMINISTRATEUR QUI PEUT REELLEMENT TOUT FAIRE, sans etre super-administrateur.
     *
     * `admin()` ne pose AUCUNE capacite. C'etait sans consequence tant que rien ne les verifiait --
     * une seule route d'administration sur quatre-vingt-six portait un `can:`. Depuis que
     * `EnforceModuleGate` fait appliquer ce que `config/modules.php` declare, ce compte se voit
     * refuser quinze ecrans, et une quinzaine de tests d'acces sont tombes en 403.
     *
     * DEUX FACONS DE REPARER, ET UNE SEULE EST HONNETE. Faire de `admin()` un compte tout-puissant
     * aurait rendu vert d'un coup, et aurait silencieusement desarme tout test verifiant qu'une
     * capacite MANQUANTE ferme une porte. On ajoute donc un etat qui DIT ce qu'il est : les tests
     * qui balaient l'espace l'emploient, ceux qui mesurent une restriction construisent leur compte
     * a la main.
     *
     * On n'en fait pas un super-administrateur non plus : celui-la passe TOUS les gardes, y compris
     * ceux qu'un test voudrait eprouver.
     */
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

    /**
     * Client personal — creates the customer_profiles row separately if needed.
     * La colonne `role` existe TOUJOURS et cet état la pose : voir la note du 2026-08-22.
     */
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

    /**
     * Provider / employe — la colonne `role` existe toujours et cet état la pose.
     * A provider_profiles row with provider_type='independent' must be created separately in tests.
     */
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

    /**
     * Company client — la colonne `role` existe toujours et cet état la pose.
     * A customer_profiles row with customer_type='company' must be created separately in tests.
     */
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

    /**
     * UNE SOCIÉTÉ CLIENTE COMPLÈTE — et « complète » n'est pas un luxe.
     *
     * Quatre choses doivent exister ensemble pour qu'un espace société s'ouvre, et
     * chaque test les rebâtissait à la main, souvent à moitié :
     *
     *   1. l'organisation, de type `client_company` ;
     *   2. le RATTACHEMENT de l'utilisateur à cette organisation — dans les DEUX
     *      colonnes, parce que `organizationContextId()` lit l'une puis l'autre ;
     *   3. le profil client `company`, que `isClientCompany()` consulte en premier ;
     *   4. l'adhésion ACTIVE, seule chose que `EnforcesActiveOrgMembership` regarde.
     *
     * Il en manquait toujours une : d'où des 403 que l'on prenait pour des défauts du
     * code alors qu'ils mesuraient une fixture incomplète.
     *
     * `forceFill` ET NON un tableau passé à `create()` : `organization_account_id` et
     * `current_organization_id` ne sont pas assignables en masse, et Eloquent les
     * écarte SANS RIEN DIRE — le compte paraît rattaché, il ne l'est pas.
     *
     * Le rôle d'organisation est `owner`, pas celui que la fabrique de membre tire au
     * hasard : un dirigeant a les permissions financières que les écrans de facturation
     * exigent. Un test qui veut un rôle restreint passe le sien à `organisationMembre`.
     */
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
