<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\RolePermissionsMatrix;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LOT 2 — LA MATRICE DE LA SOCIÉTÉ TROUVE ENFIN UN ÉCRIVAIN.
 *
 * `organization_role_permissions` existait depuis le 2026-08-06 et `PermissionService::memberCan()`
 * la lisait comme deuxième étage de résolution. Aucun écran, aucun endpoint ne l'écrivait : la table
 * n'était remplie que par des tests. Une capacité annoncée dans le code, inaccessible à qui devait
 * s'en servir.
 *
 * Ce fichier vérifie les deux moitiés : que l'écran écrit, et que ce qu'il écrit CHANGE la réponse
 * du service — un réglage qui ne se répercuterait pas serait pire qu'aucun réglage.
 */
class MatriceRolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $this->org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $this->org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    // ──────────────────────────────────────────────────────
    // Qui ouvre l'écran
    // ──────────────────────────────────────────────────────

    public function test_seul_le_porteur_de_la_cle_ouvre_l_ecran(): void
    {
        // `members.manage_permissions` est réservée au propriétaire par défaut : distribuer des
        // droits n'est pas inviter. Un gestionnaire qui pourrait s'attribuer n'importe quoi rendrait
        // toute la hiérarchie décorative.
        $this->actingAs($this->membre(OrganizationRole::MANAGER))
            ->get(route('provider-company.role-permissions'))
            ->assertForbidden();

        $this->actingAs($this->membre(OrganizationRole::OWNER))
            ->get(route('provider-company.role-permissions'))
            ->assertOk();
    }

    public function test_la_case_du_menu_ne_s_affiche_que_pour_le_proprietaire(): void
    {
        $routes = function (): array {
            return \App\Support\Navigation\ModuleCatalogue::pourContexte('provider-company')
                ->flatMap(fn (array $groupe) => array_column($groupe['modules'], 'route'))
                ->all();
        };

        $this->actingAs($this->membre(OrganizationRole::DISPATCHER));
        $this->assertNotContains('provider-company.role-permissions', $routes());

        $this->actingAs($this->membre(OrganizationRole::OWNER));
        $this->assertContains('provider-company.role-permissions', $routes());
    }

    public function test_un_droit_retire_pendant_la_session_ferme_l_action(): void
    {
        /*
         * Livewire ne rejoue PAS `mount()`. Sans revérification par action, un propriétaire
         * rétrogradé continuait de distribuer des droits tant que son onglet restait ouvert — sur
         * l'écran qui distribue précisément les droits.
         */
        $owner = $this->membre(OrganizationRole::OWNER);
        $this->membre(OrganizationRole::OWNER); // pour que la rétrogradation reste possible

        $composant = Livewire::actingAs($owner)->test(RolePermissionsMatrix::class);

        OrganizationMember::where('organization_account_id', $this->org->id)
            ->where('user_id', $owner->id)
            ->update(['role' => OrganizationRole::VIEWER->value]);

        app(PermissionService::class)->invalidateOrganizationCache($this->org->id);

        $composant->call('basculer', 'worker', 'team.view')->assertForbidden();

        $this->assertDatabaseCount('organization_role_permissions', 0);
    }

    // ──────────────────────────────────────────────────────
    // Ce que l'écran écrit change ce que le service répond
    // ──────────────────────────────────────────────────────

    public function test_accorder_une_cle_change_la_reponse_du_service(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $worker = $this->membre(OrganizationRole::WORKER);

        $this->assertFalse(app(PermissionService::class)->can($worker, 'team.view', $this->org));

        Livewire::actingAs($owner)->test(RolePermissionsMatrix::class)
            ->call('basculer', OrganizationRole::WORKER->value, 'team.view');

        $this->assertTrue(
            app(PermissionService::class)->can($worker->fresh(), 'team.view', $this->org),
            'Le réglage doit se répercuter immédiatement — le cache de permissions est purgé pour toute l’organisation.',
        );
    }

    public function test_retirer_une_cle_accordee_par_defaut_fonctionne_aussi(): void
    {
        /*
         * `granted` est un booléen EXPLICITE, pas une simple présence. Sans cela la matrice ne
         * saurait qu'élargir, et une société ne pourrait jamais restreindre un rôle.
         */
        $owner = $this->membre(OrganizationRole::OWNER);
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);

        $this->assertTrue(app(PermissionService::class)->can($dispatcher, 'missions.dispatch', $this->org));

        Livewire::actingAs($owner)->test(RolePermissionsMatrix::class)
            ->call('basculer', OrganizationRole::DISPATCHER->value, 'missions.dispatch');

        $this->assertFalse(app(PermissionService::class)->can($dispatcher->fresh(), 'missions.dispatch', $this->org));

        $this->assertDatabaseHas('organization_role_permissions', [
            'organization_account_id' => $this->org->id,
            'role' => OrganizationRole::DISPATCHER->value,
            'permission' => 'missions.dispatch',
            'granted' => false,
        ]);
    }

    public function test_la_bascule_est_rejouable_dans_les_deux_sens(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $worker = $this->membre(OrganizationRole::WORKER);

        $composant = Livewire::actingAs($owner)->test(RolePermissionsMatrix::class);

        $composant->call('basculer', OrganizationRole::WORKER->value, 'team.view');
        $composant->call('basculer', OrganizationRole::WORKER->value, 'team.view');

        $this->assertFalse(app(PermissionService::class)->can($worker->fresh(), 'team.view', $this->org));

        // Une seule ligne, mise à jour : la clé unique porte (org, rôle, permission).
        $this->assertDatabaseCount('organization_role_permissions', 1);
    }

    public function test_reinitialiser_rend_le_role_a_ses_reglages_d_usine(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);

        OrganizationRolePermission::create([
            'organization_account_id' => $this->org->id,
            'role' => OrganizationRole::DISPATCHER->value,
            'permission' => 'missions.dispatch',
            'granted' => false,
        ]);

        app(PermissionService::class)->invalidateOrganizationCache($this->org->id);
        $this->assertFalse(app(PermissionService::class)->can($dispatcher, 'missions.dispatch', $this->org));

        Livewire::actingAs($owner)->test(RolePermissionsMatrix::class)
            ->call('reinitialiser', OrganizationRole::DISPATCHER->value);

        $this->assertTrue(app(PermissionService::class)->can($dispatcher->fresh(), 'missions.dispatch', $this->org));
    }

    // ──────────────────────────────────────────────────────
    // Les bornes de ce qu'on peut régler
    // ──────────────────────────────────────────────────────

    public function test_le_role_proprietaire_n_est_pas_reglable(): void
    {
        /*
         * Il porte `members.manage_permissions`. Lui laisser la retirer à son propre rôle fermerait
         * cet écran à tout le monde, sans recours autre qu'une écriture en base.
         */
        $owner = $this->membre(OrganizationRole::OWNER);

        Livewire::actingAs($owner)->test(RolePermissionsMatrix::class)
            ->call('basculer', OrganizationRole::OWNER->value, 'members.manage_permissions');

        $this->assertDatabaseCount('organization_role_permissions', 0);
        $this->assertTrue(app(PermissionService::class)->can($owner->fresh(), 'members.manage_permissions', $this->org));
    }

    public function test_une_cle_inventee_n_ecrit_rien(): void
    {
        // Les deux valeurs viennent du navigateur : une case inventée écrirait une ligne que rien ne
        // relira jamais, et qu'aucun écran ne montrerait pour la retirer.
        $owner = $this->membre(OrganizationRole::OWNER);

        Livewire::actingAs($owner)->test(RolePermissionsMatrix::class)
            ->call('basculer', OrganizationRole::WORKER->value, 'missions.tout_casser');

        $this->assertDatabaseCount('organization_role_permissions', 0);
    }

    public function test_le_reglage_ne_franchit_pas_la_frontiere_des_societes(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);

        Livewire::actingAs($owner)->test(RolePermissionsMatrix::class)
            ->call('basculer', OrganizationRole::WORKER->value, 'team.view');

        $this->assertDatabaseMissing('organization_role_permissions', [
            'organization_account_id' => $autreOrg->id,
        ]);
    }

    public function test_la_matrice_affichee_part_des_reglages_d_usine(): void
    {
        // Un tableau qui n'afficherait que les réglages explicites serait vide au premier usage, et
        // laisserait croire que personne n'a de droits.
        $owner = $this->membre(OrganizationRole::OWNER);

        $matrice = Livewire::actingAs($owner)->test(RolePermissionsMatrix::class)
            ->instance()->matrice;

        $this->assertTrue($matrice[OrganizationRole::DISPATCHER->value]['missions.dispatch']);
        $this->assertFalse($matrice[OrganizationRole::WORKER->value]['missions.dispatch']);
        $this->assertArrayNotHasKey(OrganizationRole::OWNER->value, $matrice);
    }
}
