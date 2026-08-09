<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LOT 2 — CE QUE LE SERVEUR DÉCLARE À L'APPLICATION SUR LE SOUS-RÔLE.
 *
 * Le sous-rôle n'était PAS exposé : `/auth/me` ne renvoyait que `can_manage_company`, un seul
 * booléen pour onze rôles. L'application ne pouvait donc rien conditionner plus finement, et
 * recopier la matrice côté client l'aurait fait diverger de `PermissionService` au premier
 * ajustement — ou ignorer complètement la matrice qu'une société règle chez elle.
 *
 * LA PARITÉ CONNEXION / REPRISE EST L'AUTRE MOITIÉ DU SUJET. Ces deux réponses divergeaient déjà :
 * la connexion n'envoyait ni `can_manage_company` ni `organization_type`. Un défaut intermittent —
 * l'espace société s'ouvrait au redémarrage, pas à la connexion — est le plus coûteux à
 * diagnostiquer.
 */
class ContratDeRoleMobileTest extends TestCase
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

    private function membre(OrganizationRole $role, string $motDePasse = 'motdepasse-solide'): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'password' => bcrypt($motDePasse),
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
    // Le sous-rôle et ses clés
    // ──────────────────────────────────────────────────────

    public function test_auth_me_annonce_le_sous_role_et_les_cles_accordees(): void
    {
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);

        $donnees = $this->actingAs($dispatcher, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user');

        $this->assertSame('dispatcher', $donnees['organization_role']);
        $this->assertContains('missions.dispatch', $donnees['organization_permissions']);
        $this->assertContains('sites.view_all', $donnees['organization_permissions']);
    }

    public function test_les_cles_refusees_sont_absentes_et_non_a_faux(): void
    {
        /*
         * Le téléphone applique un DÉFAUT-REFUS : une clé absente vaut refusée. Envoyer les deux
         * moitiés inviterait à traiter l'absence comme un cas indécis — et c'est l'absence qui
         * arrive quand une version d'application précède une clé nouvelle.
         */
        $worker = $this->membre(OrganizationRole::WORKER);

        $cles = $this->actingAs($worker, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user.organization_permissions');

        $this->assertContains('tasks.create', $cles);
        $this->assertNotContains('missions.view_all', $cles);
        $this->assertNotContains('team.view', $cles);
    }

    public function test_la_matrice_de_la_societe_change_ce_que_le_mobile_recoit(): void
    {
        /*
         * `allPermissionsFor()` SAUTAIT L'ÉTAGE DU MILIEU : elle ne connaissait que la dérogation
         * nominative et la matrice par défaut du code, pas la matrice propre à la société. Le
         * téléphone aurait donc reçu une liste que `PermissionService::can()` contredit — et la
         * fenêtre des permissions du web affichait déjà l'état par défaut au lieu de l'effectif.
         */
        $worker = $this->membre(OrganizationRole::WORKER);

        OrganizationRolePermission::create([
            'organization_account_id' => $this->org->id,
            'role' => OrganizationRole::WORKER->value,
            'permission' => 'team.view',
            'granted' => true,
        ]);

        $cles = $this->actingAs($worker, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user.organization_permissions');

        $this->assertContains('team.view', $cles, 'La matrice de la société doit être lue.');
    }

    public function test_la_matrice_de_la_societe_peut_aussi_retirer_une_cle(): void
    {
        // `granted` est un booléen EXPLICITE, pas une simple présence : une société peut retirer un
        // droit accordé par défaut, sinon la matrice ne saurait qu'élargir.
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);

        OrganizationRolePermission::create([
            'organization_account_id' => $this->org->id,
            'role' => OrganizationRole::DISPATCHER->value,
            'permission' => 'missions.dispatch',
            'granted' => false,
        ]);

        $cles = $this->actingAs($dispatcher, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user.organization_permissions');

        $this->assertNotContains('missions.dispatch', $cles);
    }

    public function test_la_derogation_nominative_prime_sur_la_matrice_de_la_societe(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);

        OrganizationRolePermission::create([
            'organization_account_id' => $this->org->id,
            'role' => OrganizationRole::WORKER->value,
            'permission' => 'team.view',
            'granted' => false,
        ]);

        OrganizationMember::where('organization_account_id', $this->org->id)
            ->where('user_id', $worker->id)
            ->update(['permissions' => ['team.view' => true]]);

        $cles = $this->actingAs($worker->fresh(), 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user.organization_permissions');

        $this->assertContains('team.view', $cles, 'L’étage nominatif est le premier des trois.');
    }

    public function test_le_contrat_reste_coherent_avec_le_service_de_permissions(): void
    {
        /*
         * LA VÉRIFICATION QUI COMPTE VRAIMENT. Deux chemins répondent à « a-t-il ce droit » :
         * `can()` côté serveur, et la liste envoyée au téléphone. S'ils divergent, le mobile
         * affiche des boutons que l'API refuse — ou cache des écrans autorisés.
         */
        $chefDEquipe = $this->membre(OrganizationRole::TEAM_LEAD);

        $cles = $this->actingAs($chefDEquipe, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user.organization_permissions');

        $service = app(PermissionService::class);

        foreach ($service->allPermissionKeys() as $cle) {
            $this->assertSame(
                $service->can($chefDEquipe, $cle, $this->org),
                in_array($cle, $cles, true),
                "La clé {$cle} n’est pas déclarée comme le service la résout.",
            );
        }
    }

    public function test_un_compte_sans_organisation_ne_recoit_aucune_cle(): void
    {
        $independant = User::factory()->employe()->create(['email_verified_at' => now()]);

        $donnees = $this->actingAs($independant, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user');

        $this->assertNull($donnees['organization_role']);
        $this->assertSame([], $donnees['organization_permissions']);
        $this->assertFalse($donnees['can_manage_company']);
    }

    public function test_une_adhesion_suspendue_ne_donne_plus_aucune_cle(): void
    {
        /*
         * L'organisation résolue ne prouve pas l'appartenance : `current_organization_id` reste
         * renseigné après une suspension. Sans exigence d'adhésion ACTIVE, un compte écarté gardait
         * son sous-rôle et ses clés sur son téléphone.
         */
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);

        OrganizationMember::where('organization_account_id', $this->org->id)
            ->where('user_id', $dispatcher->id)
            ->update(['status' => 'suspended']);

        $donnees = $this->actingAs($dispatcher->fresh(), 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user');

        $this->assertNull($donnees['organization_role']);
        $this->assertSame([], $donnees['organization_permissions']);
    }

    // ──────────────────────────────────────────────────────
    // Parité connexion / reprise de session
    // ──────────────────────────────────────────────────────

    public function test_la_connexion_declare_le_meme_contrat_que_la_reprise(): void
    {
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);

        $aLaConnexion = $this->postJson('/api/auth/login', [
            'email' => $dispatcher->email,
            'password' => 'motdepasse-solide',
        ])->assertOk()->json('user');

        $aLaReprise = $this->actingAs($dispatcher, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('user');

        foreach ([
            'organization_account_id',
            'organization_type',
            'organization_role',
            'organization_permissions',
            'can_manage_company',
        ] as $champ) {
            $this->assertSame(
                $aLaReprise[$champ],
                $aLaConnexion[$champ] ?? null,
                "Le champ {$champ} diffère entre la connexion et la reprise de session.",
            );
        }
    }

    public function test_la_connexion_annonce_desormais_l_espace_a_ouvrir(): void
    {
        /*
         * `serializeUser()` n'envoyait NI `organization_type` NI `can_manage_company` : à la
         * connexion, l'application ne savait pas quel espace ouvrir pour un dirigeant de société —
         * et l'obtenait au redémarrage suivant. Un défaut intermittent dont la cause est invisible.
         */
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->postJson('/api/auth/login', [
            'email' => $owner->email,
            'password' => 'motdepasse-solide',
        ])
            ->assertOk()
            ->assertJsonPath('user.organization_type', OrganizationType::PROVIDER_COMPANY->value)
            ->assertJsonPath('user.can_manage_company', true)
            ->assertJsonPath('user.organization_role', 'owner');
    }
}
