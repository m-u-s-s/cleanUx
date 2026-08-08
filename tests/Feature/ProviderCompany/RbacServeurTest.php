<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LOT 1 — RBAC SERVEUR : chaque membre n'accède qu'à ce que son rôle permet.
 *
 * Les gardes sont à DEUX ÉTAGES : middleware `org.permission:<clé>` sur les LECTURES de groupes de
 * routes — uniforme, impossible à oublier — et vérification fine dans chaque action d'écriture.
 * Ce fichier exerce l'étage middleware et la politique mission.
 *
 * L'EXIGENCE 8 EST UNE GARDE SERVEUR, PAS UN FILTRE D'ÉCRAN. Un worker ne doit pas pouvoir suivre
 * une mission qui ne lui est pas assignée « ni à l'écran, ni via l'API » : masquer le bouton ne
 * suffit pas, l'URL reste tapable.
 */
class RbacServeurTest extends TestCase
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

    private function membre(OrganizationRole $role, ?OrganizationAccount $org = null): User
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

        OrganizationMember::create([
            'organization_account_id' => $org->id,
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
    // Le middleware, enregistré et résolvant la bonne organisation
    // ──────────────────────────────────────────────────────

    public function test_l_alias_org_permission_est_enregistre(): void
    {
        // Le middleware était écrit mais absent du Kernel : toute route qui l'invoquait plantait
        // sur un alias inconnu, et personne ne s'en apercevait faute d'appelant.
        $alias = app(\Illuminate\Contracts\Http\Kernel::class)->getRouteMiddleware();

        $this->assertArrayHasKey('org.permission', $alias);
        $this->assertSame(\App\Http\Middleware\CheckOrganizationPermission::class, $alias['org.permission']);
    }

    // ──────────────────────────────────────────────────────
    // API société : lectures gardées
    // ──────────────────────────────────────────────────────

    public function test_le_worker_est_refuse_sur_les_lectures_de_pilotage(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);

        foreach (['overview', 'members', 'sites', 'field-teams'] as $chemin) {
            $this->actingAs($worker, 'sanctum')
                ->getJson("/api/provider/company/{$chemin}")
                ->assertForbidden();
        }
    }

    public function test_l_owner_accede_aux_lectures_de_pilotage(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);

        foreach (['overview', 'members', 'sites', 'field-teams'] as $chemin) {
            $this->actingAs($owner, 'sanctum')
                ->getJson("/api/provider/company/{$chemin}")
                ->assertOk();
        }
    }

    public function test_le_dispatcher_lit_les_sites_desservis(): void
    {
        // `sites.view_all` est ajoutée à la matrice pour operations_manager, dispatcher et
        // site_manager : elle était déclarée dans `SiteOperations` sans être accordée à personne.
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);

        $this->actingAs($dispatcher, 'sanctum')
            ->getJson('/api/provider/company/sites')
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────
    // MissionPolicy — l'exigence 8
    // ──────────────────────────────────────────────────────

    private function missionDeLOrg(?OrganizationAccount $org = null): Mission
    {
        return Mission::factory()->create([
            'provider_organization_id' => ($org ?? $this->org)->id,
        ]);
    }

    public function test_l_owner_ouvre_une_mission_de_sa_societe(): void
    {
        /*
         * `MissionPolicy` ignorait l'organisation : un owner ne pouvait pas ouvrir
         * `/missions/{id}` de sa PROPRE société. La garde regardait le rôle plateforme, pas
         * l'appartenance.
         */
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->assertTrue($owner->can('view', $this->missionDeLOrg()));
    }

    public function test_le_worker_ne_voit_pas_une_mission_qui_n_est_pas_la_sienne(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);

        $this->assertFalse($worker->can('view', $this->missionDeLOrg()));
    }

    public function test_le_worker_voit_la_mission_qui_lui_est_assignee(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $mission = $this->missionDeLOrg();

        $mission->assignments()->create([
            'user_id' => $worker->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $this->assertTrue($worker->fresh()->can('view', $mission->fresh()));
    }

    public function test_un_owner_ne_voit_pas_la_mission_d_une_autre_societe(): void
    {
        // L'anti-fuite entre sociétés concurrentes : le rang ne vaut que DANS son organisation.
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);

        $this->assertFalse($owner->can('view', $this->missionDeLOrg($autreOrg)));
    }

    // ──────────────────────────────────────────────────────
    // Web : la navbar ne promet plus ce qu'elle ne peut pas ouvrir
    // ──────────────────────────────────────────────────────

    /** @return list<string> */
    private function routesDuMenuSociete(): array
    {
        return \App\Support\Navigation\ModuleCatalogue::pourContexte('provider-company')
            ->flatMap(fn (array $groupe) => array_column($groupe['modules'], 'route'))
            ->all();
    }

    public function test_la_navbar_du_worker_n_a_plus_de_lien_mort(): void
    {
        /*
         * Une case qui mène à un 403 est pire qu'une case absente. Depuis que les lectures sont
         * gardées, la navbar d'un worker affichait quatre liens qui répondent 403.
         */
        $this->actingAs($this->membre(OrganizationRole::WORKER));

        $routes = $this->routesDuMenuSociete();

        foreach ([
            'provider-company.dispatch',
            'provider-company.team',
            'provider-company.field-teams',
            'provider-company.sites',
        ] as $interdite) {
            $this->assertNotContains($interdite, $routes, $interdite.' ne doit pas figurer au menu d’un worker');
        }
    }

    public function test_la_navbar_de_l_owner_garde_tout(): void
    {
        $this->actingAs($this->membre(OrganizationRole::OWNER));

        $routes = $this->routesDuMenuSociete();

        foreach (['provider-company.dispatch', 'provider-company.team', 'provider-company.sites'] as $attendue) {
            $this->assertContains($attendue, $routes, $attendue);
        }
    }

    public function test_le_worker_est_refuse_sur_l_ecran_des_sites(): void
    {
        // `sites.view_all` était DÉCLARÉE par `SiteOperations` et jamais consultée : tout membre
        // actif voyait les sites clients desservis, leurs référents et leurs contrats-cadres.
        $this->actingAs($this->membre(OrganizationRole::WORKER))
            ->get(route('provider-company.sites'))
            ->assertForbidden();
    }

    public function test_l_owner_ouvre_l_ecran_des_sites(): void
    {
        $this->actingAs($this->membre(OrganizationRole::OWNER))
            ->get(route('provider-company.sites'))
            ->assertOk();
    }
}
