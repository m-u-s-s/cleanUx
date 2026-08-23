<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Channel;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** LOT 2 — GÉRER SON ÉQUIPE DEPUIS LE TÉLÉPHONE. */
class ApiAdministrationMembresTest extends TestCase
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

    private function membre(OrganizationRole $role, ?OrganizationAccount $org = null): OrganizationMember
    {
        $org ??= $this->org;

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        return OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    private function utilisateurDe(OrganizationMember $membre): User
    {
        return User::findOrFail($membre->user_id);
    }

    // ──────────────────────────────────────────────────────
    // Changement de sous-rôle
    // ──────────────────────────────────────────────────────

    public function test_l_owner_change_un_sous_role_depuis_le_telephone(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $cible = $this->membre(OrganizationRole::WORKER);

        $this->actingAs($this->utilisateurDe($owner), 'sanctum')
            ->patchJson("/api/provider/company/members/{$cible->id}/role", ['role' => 'dispatcher'])
            ->assertOk()
            ->assertJsonPath('data.role', 'dispatcher');

        $this->assertSame('dispatcher', $cible->fresh()->role->value);
    }

    public function test_le_worker_ne_change_le_role_de_personne(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $cible = $this->membre(OrganizationRole::WORKER);

        $this->actingAs($this->utilisateurDe($worker), 'sanctum')
            ->patchJson("/api/provider/company/members/{$cible->id}/role", ['role' => 'dispatcher'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'permission');

        $this->assertSame('worker', $cible->fresh()->role->value);
    }

    public function test_on_ne_declasse_pas_un_rang_superieur_au_sien(): void
    {
        // Le trou fermé le 2026-08-06 côté web : seul le rang du NOUVEAU rôle était comparé, si bien qu'un directeur d'opérations pouvait déclasser un PROPRIÉTAIRE en nettoyeur.
        $ops = $this->membre(OrganizationRole::OPERATIONS_MANAGER);
        $this->utilisateurDe($ops); // acteur
        $owner = $this->membre(OrganizationRole::OWNER);

        OrganizationMember::whereKey($ops->id)->update(['permissions' => ['members.edit_role' => true]]);
        app(PermissionService::class)->invalidateOrganizationCache($this->org->id);

        $this->actingAs($this->utilisateurDe($ops), 'sanctum')
            ->patchJson("/api/provider/company/members/{$owner->id}/role", ['role' => 'worker'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'hierarchie');

        $this->assertSame('owner', $owner->fresh()->role->value);
    }

    public function test_on_ne_promeut_personne_a_son_propre_rang(): void
    {
        // Sans cette règle, distribuer un rôle deviendrait un moyen de se donner un supérieur
        // complaisant — ou de se faire nommer par un complice.
        $ops = $this->membre(OrganizationRole::OPERATIONS_MANAGER);
        $cible = $this->membre(OrganizationRole::WORKER);

        OrganizationMember::whereKey($ops->id)->update(['permissions' => ['members.edit_role' => true]]);
        app(PermissionService::class)->invalidateOrganizationCache($this->org->id);

        $this->actingAs($this->utilisateurDe($ops), 'sanctum')
            ->patchJson("/api/provider/company/members/{$cible->id}/role", ['role' => 'owner'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'promotion_trop_haute');
    }

    public function test_le_dernier_proprietaire_ne_se_declasse_pas(): void
    {
        // Une société sans propriétaire actif n'a plus personne pour inviter, facturer ou céder ses droits, et aucun écran ne permet d'en nommer un depuis l'extérieur : l'enfermement serait définitif.
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($this->utilisateurDe($owner), 'sanctum')
            ->patchJson("/api/provider/company/members/{$owner->id}/role", ['role' => 'worker'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'dernier_proprietaire');

        $this->assertSame('owner', $owner->fresh()->role->value);
    }

    public function test_un_role_inconnu_est_refuse_sans_erreur_serveur(): void
    {
        // `OrganizationRole::from()` lève un `ValueError` : 500 sur une saisie utilisateur. C'est le
        // défaut que le lot 1 a fermé sur l'invitation ; il ne revient pas par l'API.
        $owner = $this->membre(OrganizationRole::OWNER);
        $cible = $this->membre(OrganizationRole::WORKER);

        $this->actingAs($this->utilisateurDe($owner), 'sanctum')
            ->patchJson("/api/provider/company/members/{$cible->id}/role", ['role' => 'empereur'])
            ->assertStatus(422);
    }

    public function test_un_membre_d_une_autre_societe_est_introuvable(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);
        $etranger = $this->membre(OrganizationRole::WORKER, $autreOrg);

        $this->actingAs($this->utilisateurDe($owner), 'sanctum')
            ->patchJson("/api/provider/company/members/{$etranger->id}/role", ['role' => 'dispatcher'])
            ->assertNotFound();

        $this->assertSame('worker', $etranger->fresh()->role->value);
    }

    // ──────────────────────────────────────────────────────
    // Suspension, réactivation, départ
    // ──────────────────────────────────────────────────────

    public function test_l_owner_suspend_puis_reactive_un_membre(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $cible = $this->membre(OrganizationRole::WORKER);
        $acteur = $this->utilisateurDe($owner);

        $this->actingAs($acteur, 'sanctum')
            ->postJson("/api/provider/company/members/{$cible->id}/suspend")
            ->assertOk();
        $this->assertSame('suspended', $cible->fresh()->status);

        $this->actingAs($acteur, 'sanctum')
            ->postJson("/api/provider/company/members/{$cible->id}/reactivate")
            ->assertOk();
        $this->assertSame('active', $cible->fresh()->status);
    }

    public function test_on_ne_se_suspend_pas_soi_meme(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $this->membre(OrganizationRole::OWNER); // un second, pour que seule l'auto-action bloque

        $this->actingAs($this->utilisateurDe($owner), 'sanctum')
            ->postJson("/api/provider/company/members/{$owner->id}/suspend")
            ->assertStatus(422)
            ->assertJsonPath('reason', 'soi_meme');

        $this->assertSame('active', $owner->fresh()->status);
    }

    public function test_le_depart_libere_les_missions_a_venir_et_les_canaux(): void
    {
        // UN DÉPART NE SE CONTENTE PAS DE CHANGER UN STATUT.
        $owner = $this->membre(OrganizationRole::OWNER);
        $partant = $this->membre(OrganizationRole::WORKER);

        $missionFuture = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'planned_start_at' => now()->addWeek(),
            'lead_provider_user_id' => $partant->user_id,
        ]);
        $missionFuture->assignments()->create([
            'user_id' => $partant->user_id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $missionPassee = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'planned_start_at' => now()->subWeek(),
            'lead_provider_user_id' => $partant->user_id,
        ]);

        $canal = Channel::factory()->create(['organization_account_id' => $this->org->id]);
        DB::table('channel_members')->insert([
            'channel_id' => $canal->id,
            'user_id' => $partant->user_id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->utilisateurDe($owner), 'sanctum')
            ->deleteJson("/api/provider/company/members/{$partant->id}")
            ->assertOk();

        $this->assertSame('left', $partant->fresh()->status);
        $this->assertNull($missionFuture->fresh()->lead_provider_user_id);
        $this->assertSame(
            'released',
            $missionFuture->assignments()->where('user_id', $partant->user_id)->first()?->assignment_status,
        );

        // Le PASSÉ ne bouge pas : il dit qui a réalisé quoi, et la facturation s'y appuie.
        $this->assertSame($partant->user_id, $missionPassee->fresh()->lead_provider_user_id);

        $this->assertDatabaseMissing('channel_members', [
            'channel_id' => $canal->id,
            'user_id' => $partant->user_id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Matrice rôle → permissions
    // ──────────────────────────────────────────────────────

    public function test_l_owner_lit_et_regle_la_matrice_depuis_le_telephone(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $worker = $this->membre(OrganizationRole::WORKER);
        $acteur = $this->utilisateurDe($owner);

        // Pas d'`assertJsonPath` ici : les clés de permission CONTIENNENT un point (`missions.dispatch`), que la notation par chemin lit comme un niveau imbriqué.
        $matrice = $this->actingAs($acteur, 'sanctum')
            ->getJson('/api/provider/company/role-permissions')
            ->assertOk()
            ->json('data.matrix');

        $this->assertTrue($matrice['dispatcher']['missions.dispatch']);
        $this->assertFalse($matrice['worker']['missions.dispatch']);
        $this->assertArrayNotHasKey('owner', $matrice);

        $this->actingAs($acteur, 'sanctum')
            ->putJson('/api/provider/company/role-permissions', [
                'role' => 'worker',
                'permission' => 'team.view',
                'granted' => true,
            ])
            ->assertOk();

        $this->assertTrue(
            app(PermissionService::class)->can($this->utilisateurDe($worker), 'team.view', $this->org),
        );
    }

    public function test_seul_le_porteur_de_la_cle_touche_a_la_matrice(): void
    {
        $manager = $this->membre(OrganizationRole::MANAGER);

        $this->actingAs($this->utilisateurDe($manager), 'sanctum')
            ->getJson('/api/provider/company/role-permissions')
            ->assertForbidden();

        $this->actingAs($this->utilisateurDe($manager), 'sanctum')
            ->putJson('/api/provider/company/role-permissions', [
                'role' => 'worker',
                'permission' => 'team.view',
                'granted' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('organization_role_permissions', 0);
    }

    public function test_le_role_proprietaire_reste_hors_matrice_aussi_par_l_api(): void
    {
        // La borne du web doit tenir ici : sans elle, le téléphone rouvrirait le passage que
        // l'écran ferme, et un propriétaire pourrait se retirer la clé qui ouvre l'écran.
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($this->utilisateurDe($owner), 'sanctum')
            ->putJson('/api/provider/company/role-permissions', [
                'role' => 'owner',
                'permission' => 'members.manage_permissions',
                'granted' => false,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('organization_role_permissions', 0);
    }
}
