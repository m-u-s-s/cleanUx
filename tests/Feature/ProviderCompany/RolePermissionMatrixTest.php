<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** CHAQUE SOCIÉTÉ DOIT POUVOIR RÉGLER SA PROPRE MATRICE DE RÔLES. POURQUOI CE FICHIER EXISTE. */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: OrganizationMember} */
    private function societeAvecChefDEquipe(): array
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        $membre = OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::TEAM_LEAD->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $membre];
    }

    #[Test]
    public function sans_reglage_le_defaut_du_code_s_applique(): void
    {
        [$org, $membre] = $this->societeAvecChefDEquipe();

        // `members.invite` n'est pas accordé au chef d'équipe par défaut.
        $this->assertFalse(
            app(PermissionService::class)->can($membre->user, 'members.invite', $org),
            "Le comportement par défaut doit rester strictement inchangé tant qu'aucune matrice n'est réglée.",
        );
    }

    #[Test]
    public function une_societe_peut_accorder_un_droit_a_un_role(): void
    {
        [$org, $membre] = $this->societeAvecChefDEquipe();

        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::TEAM_LEAD->value,
            'permission' => 'members.invite',
            'granted' => true,
        ]);

        app(PermissionService::class)->invalidateOrganizationCache($org->id);

        $this->assertTrue(
            app(PermissionService::class)->can($membre->user, 'members.invite', $org),
            'Une société doit pouvoir élargir un rôle sans attendre un déploiement.',
        );
    }

    #[Test]
    public function une_societe_peut_retirer_un_droit_a_un_role(): void
    {
        [$org, $membre] = $this->societeAvecChefDEquipe();

        // `tasks.create` fait partie des défauts du chef d'équipe.
        $this->assertTrue(app(PermissionService::class)->can($membre->user, 'tasks.create', $org));

        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::TEAM_LEAD->value,
            'permission' => 'tasks.create',
            'granted' => false,
        ]);

        app(PermissionService::class)->invalidateOrganizationCache($org->id);

        $this->assertFalse(
            app(PermissionService::class)->can($membre->user, 'tasks.create', $org),
            'Restreindre doit être possible autant qu\'élargir.',
        );
    }

    #[Test]
    public function le_reglage_d_une_societe_ne_deborde_pas_sur_une_autre(): void
    {
        [$org, $membre] = $this->societeAvecChefDEquipe();
        [$autreOrg, $autreMembre] = $this->societeAvecChefDEquipe();

        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::TEAM_LEAD->value,
            'permission' => 'members.invite',
            'granted' => true,
        ]);

        app(PermissionService::class)->invalidateOrganizationCache($org->id);

        $this->assertTrue(app(PermissionService::class)->can($membre->user, 'members.invite', $org));
        $this->assertFalse(
            app(PermissionService::class)->can($autreMembre->user, 'members.invite', $autreOrg),
            "Une matrice est propre à sa société : l'élargir chez l'une ne doit rien changer chez l'autre.",
        );
    }

    #[Test]
    public function la_surcharge_par_membre_prime_sur_la_matrice_de_la_societe(): void
    {
        [$org, $membre] = $this->societeAvecChefDEquipe();

        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::TEAM_LEAD->value,
            'permission' => 'members.invite',
            'granted' => true,
        ]);

        // Ce membre-là, précisément, ne doit pas l'avoir.
        $membre->update(['permissions' => ['members.invite' => false]]);

        app(PermissionService::class)->invalidateOrganizationCache($org->id);

        $this->assertFalse(
            app(PermissionService::class)->can($membre->user, 'members.invite', $org),
            'La décision prise sur une personne doit rester plus forte que la règle générale.',
        );
    }
}
