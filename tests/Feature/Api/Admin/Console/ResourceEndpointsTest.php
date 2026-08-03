<?php

namespace Tests\Feature\Api\Admin\Console;

use App\Admin\Console\ResourceRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les endpoints génériques du moteur de console.
 *
 * Ils sont éprouvés sur un descripteur d'essai plutôt que sur un vrai domaine : les défauts du
 * moteur et ceux d'un domaine se mêleraient sinon, et un test rouge ne dirait pas lequel des deux
 * est en cause.
 */
class ResourceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeUserResource::$executed = [];
        app(ResourceRegistry::class)->register('fake-users', FakeUserResource::class);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create(['name' => 'Zoé Admin']);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    // ── Le descripteur servi ────────────────────────────────────────────────────────────────

    public function test_l_index_sert_le_descripteur_avec_les_donnees(): void
    {
        $this->actingAsAdmin();

        $res = $this->getJson('/api/admin/console/fake-users')->assertOk();

        $res->assertJsonPath('ok', true);
        $this->assertSame(['name', 'email', 'created_at', 'is_active'],
            array_column($res->json('resource.columns'), 'key'));
        $this->assertSame(['q', 'role', 'actif'], array_column($res->json('resource.filters'), 'key'));
        $this->assertSame(['id', 'name', 'created_at'], $res->json('resource.sorts'));
        $this->assertSame(['ping', 'suspend'], array_column($res->json('resource.actions'), 'key'));
        $this->assertSame(['name', 'email', 'password'], array_column($res->json('resource.form'), 'key'));
    }

    public function test_le_descripteur_ne_publie_jamais_de_code_executable(): void
    {
        $this->actingAsAdmin();

        $actions = $this->getJson('/api/admin/console/fake-users')->json('resource.actions');

        foreach ($actions as $action) {
            // `fields` décrit ce que l'action EXIGE avant de s'exécuter — des champs à dessiner,
            // jamais du comportement. La fermeture, elle, ne traverse jamais le JSON.
            $this->assertSame(['key', 'label', 'destructive', 'confirm', 'fields'], array_keys($action));
            $this->assertIsArray($action['fields']);
        }
    }

    public function test_une_ressource_inconnue_rend_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/console/licornes')
            ->assertStatus(404)
            ->assertJsonPath('error', 'unknown_resource');
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']), ['*']);

        $this->getJson('/api/admin/console/fake-users')
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_not_admin');
    }

    // ── Filtres, tri, pagination ────────────────────────────────────────────────────────────

    public function test_un_filtre_declare_est_applique(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['name' => 'Bertrand Cible']);
        User::factory()->create(['name' => 'Personne Autre']);

        $rows = $this->getJson('/api/admin/console/fake-users?filters[q]=Cible')->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('Bertrand Cible', $rows[0]['name']);
    }

    public function test_un_filtre_inconnu_est_ignore_et_non_devine(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(2)->create();

        // Deviner une colonne depuis la clé transmise laisserait le client filtrer sur n'importe
        // quel champ — y compris ceux que le descripteur ne veut pas exposer.
        $rows = $this->getJson('/api/admin/console/fake-users?filters[password]=secret')->json('rows');

        $this->assertSame(User::count(), count($rows));
    }

    public function test_un_tri_declare_est_applique(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['name' => 'Amélie']);
        User::factory()->create(['name' => 'Bertrand']);

        $rows = $this->getJson('/api/admin/console/fake-users?sort=name&direction=asc')->json('rows');

        $noms = array_column($rows, 'name');
        $tries = $noms;
        sort($tries);
        $this->assertSame($tries, $noms);
    }

    public function test_un_tri_non_declare_est_refuse(): void
    {
        $this->actingAsAdmin();

        // La clé de tri arrive du client : la transmettre sans la vérifier ouvrirait la porte à
        // un tri sur une colonne non exposée, voire à une injection selon le pilote.
        $this->getJson('/api/admin/console/fake-users?sort=password')
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_sort');
    }

    public function test_une_direction_de_tri_invalide_est_refusee(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/console/fake-users?sort=name&direction=drop')
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_direction');
    }

    public function test_la_pagination_se_fait_par_curseur(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(30)->create();

        $premiere = $this->getJson('/api/admin/console/fake-users?per_page=10')->assertOk();
        $this->assertCount(10, $premiere->json('rows'));
        $this->assertNotNull($premiere->json('next_cursor'));

        $seconde = $this->getJson('/api/admin/console/fake-users?per_page=10&cursor='.$premiere->json('next_cursor'))
            ->assertOk();

        // Aucune ligne ne doit apparaître dans les deux pages : c'est précisément ce qu'un offset
        // ne garantit pas sur une table qui bouge.
        $ids1 = array_column($premiere->json('rows'), 'id');
        $ids2 = array_column($seconde->json('rows'), 'id');
        $this->assertSame([], array_intersect($ids1, $ids2));
    }

    public function test_la_taille_de_page_est_bornee(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(5)->create();

        // Sans borne, un client pourrait demander 100 000 lignes et faire tomber le serveur.
        $res = $this->getJson('/api/admin/console/fake-users?per_page=100000')->assertOk();

        $this->assertLessThanOrEqual(100, count($res->json('rows')));
    }

    // ── Détail ──────────────────────────────────────────────────────────────────────────────

    public function test_le_detail_rend_plus_que_la_ligne(): void
    {
        $admin = $this->actingAsAdmin();

        $res = $this->getJson("/api/admin/console/fake-users/{$admin->id}")->assertOk();

        $this->assertSame('Zoé Admin', $res->json('row.name'));
        $this->assertArrayHasKey('role', $res->json('row'));
    }

    public function test_un_detail_introuvable_rend_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/console/fake-users/999999')
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    // ── Écriture ────────────────────────────────────────────────────────────────────────────

    public function test_la_creation_valide_avec_les_regles_du_descripteur(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/console/fake-users', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonStructure(['ok', 'errors' => ['name', 'email', 'password']]);
    }

    public function test_la_creation_ignore_les_champs_non_declares(): void
    {
        $this->actingAsAdmin();

        $res = $this->postJson('/api/admin/console/fake-users', [
            'name' => 'Nouvelle Personne',
            'email' => 'nouvelle@example.test',
            'password' => 'motdepasse123',
            // Non déclaré au formulaire : le laisser passer permettrait de se promouvoir.
            'platform_role' => 'super_admin',
        ])->assertStatus(201);

        $cree = User::find($res->json('row.id'));
        $this->assertSame('Nouvelle Personne', $cree->name);
        $this->assertNotSame('super_admin', $cree->platform_role);
    }

    public function test_la_mise_a_jour_applique_les_champs_declares(): void
    {
        $admin = $this->actingAsAdmin();

        $this->patchJson("/api/admin/console/fake-users/{$admin->id}", ['name' => 'Zoé Renommée'])
            ->assertOk()
            ->assertJsonPath('row.name', 'Zoé Renommée');

        $this->assertSame('Zoé Renommée', $admin->fresh()->name);
    }

    public function test_la_suppression_retire_la_ligne(): void
    {
        $this->actingAsAdmin();
        $cible = User::factory()->create();

        $this->deleteJson("/api/admin/console/fake-users/{$cible->id}")->assertOk();

        $this->assertNull(User::find($cible->id));
    }

    // ── Actions ─────────────────────────────────────────────────────────────────────────────

    public function test_une_action_est_deleguee_au_descripteur(): void
    {
        $admin = $this->actingAsAdmin();

        $this->postJson("/api/admin/console/fake-users/{$admin->id}/actions/ping")
            ->assertOk()
            ->assertJsonPath('result.message', 'pong');

        $this->assertSame([['ping', $admin->id]], FakeUserResource::$executed);
    }

    public function test_une_action_inconnue_rend_404(): void
    {
        $admin = $this->actingAsAdmin();

        $this->postJson("/api/admin/console/fake-users/{$admin->id}/actions/autodestruction")
            ->assertStatus(404)
            ->assertJsonPath('error', 'unknown_action');

        $this->assertSame([], FakeUserResource::$executed);
    }

    public function test_une_action_destructive_s_execute_quand_elle_est_demandee(): void
    {
        $admin = $this->actingAsAdmin();

        // La confirmation est une affaire d'INTERFACE : le serveur annonce `destructive` et le
        // texte, le mobile demande confirmation. Exiger un jeton de confirmation côté serveur
        // n'ajouterait aucune sécurité — l'appel vient déjà d'un administrateur authentifié.
        $this->postJson("/api/admin/console/fake-users/{$admin->id}/actions/suspend")->assertOk();

        $this->assertSame([['suspend', $admin->id]], FakeUserResource::$executed);
    }
}
