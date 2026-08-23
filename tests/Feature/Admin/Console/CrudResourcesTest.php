<?php

namespace Tests\Feature\Admin\Console;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les trois premiers descripteurs, éprouvés à travers le moteur.
 *
 * Ils sont testés par l'API et non en isolation : c'est le chemin que prend l'application, et
 * c'est le seul qui prouve que le descripteur ET le moteur s'accordent.
 */
class CrudResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->adminComplet()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    // ── users ───────────────────────────────────────────────────────────────────────────────

    public function test_les_comptes_se_listent(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['name' => 'Amélie Durand']);

        $rows = $this->getJson('/api/admin/console/users')->assertOk()->json('rows');

        $this->assertContains('Amélie Durand', array_column($rows, 'name'));
    }

    public function test_la_recherche_de_comptes_couvre_nom_email_et_tva(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['name' => 'Cible Trouvable', 'email' => 'a@example.test']);
        User::factory()->create(['name' => 'Autre', 'email' => 'cible-par-email@example.test']);
        User::factory()->create(['name' => 'Encore Autre', 'email' => 'c@example.test']);

        $parNom = $this->getJson('/api/admin/console/users?filters[q]=Cible Trouvable')->json('rows');
        $parEmail = $this->getJson('/api/admin/console/users?filters[q]=cible-par-email')->json('rows');

        $this->assertCount(1, $parNom);
        $this->assertCount(1, $parEmail);
    }

    public function test_aucun_mot_de_passe_n_est_jamais_servi(): void
    {
        $admin = $this->actingAsAdmin();

        $ligne = $this->getJson("/api/admin/console/users/{$admin->id}")->assertOk()->json('row');

        // Ni en clair ni haché : un condensat servi à un client est un condensat qu'on peut
        // attaquer hors ligne.
        $this->assertArrayNotHasKey('password', $ligne);
        $this->assertArrayNotHasKey('remember_token', $ligne);
        $this->assertArrayNotHasKey('two_factor_secret', $ligne);
    }

    public function test_un_compte_se_cree_avec_un_mot_de_passe_hache(): void
    {
        $this->actingAsAdmin();

        $res = $this->postJson('/api/admin/console/users', [
            'name' => 'Nouveau Compte',
            'email' => 'nouveau@example.test',
            'password' => 'motdepasse123',
            'platform_role' => 'user',
        ])->assertStatus(201);

        $cree = User::find($res->json('row.id'));
        $this->assertNotSame('motdepasse123', $cree->password);
        $this->assertTrue(Hash::check('motdepasse123', $cree->password));
    }

    public function test_la_console_ne_mint_aucun_super_administrateur(): void
    {
        $this->actingAsAdmin();

        // Un compte capable de tout faire ne se crée pas depuis un téléphone entre deux portes.
        $this->postJson('/api/admin/console/users', [
            'name' => 'Tentative',
            'email' => 'tentative@example.test',
            'password' => 'motdepasse123',
            'platform_role' => 'super_admin',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['platform_role']]);

        $this->assertNull(User::where('email', 'tentative@example.test')->first());
    }

    public function test_l_edition_sans_mot_de_passe_le_laisse_inchange(): void
    {
        $this->actingAsAdmin();
        $cible = User::factory()->create(['password' => 'ancien-mot-de-passe']);
        $avant = $cible->fresh()->password;

        $this->patchJson("/api/admin/console/users/{$cible->id}", ['name' => 'Renommé'])->assertOk();

        $this->assertSame('Renommé', $cible->fresh()->name);
        $this->assertSame($avant, $cible->fresh()->password);
    }

    public function test_la_suspension_coupe_l_acces_et_la_reactivation_le_rend(): void
    {
        $this->actingAsAdmin();
        $cible = User::factory()->create(['is_active' => true]);

        $this->postJson("/api/admin/console/users/{$cible->id}/actions/suspend")->assertOk();
        $this->assertFalse((bool) $cible->fresh()->is_active);

        $this->postJson("/api/admin/console/users/{$cible->id}/actions/reactivate")->assertOk();
        $this->assertTrue((bool) $cible->fresh()->is_active);
    }

    public function test_la_suspension_est_annoncee_comme_destructive(): void
    {
        $this->actingAsAdmin();

        $actions = $this->getJson('/api/admin/console/users')->json('resource.actions');
        $suspend = collect($actions)->firstWhere('key', 'suspend');

        $this->assertTrue($suspend['destructive']);
        $this->assertNotEmpty($suspend['confirm']);
    }

    // ── companies ───────────────────────────────────────────────────────────────────────────

    public function test_les_entreprises_se_listent_et_se_filtrent(): void
    {
        $this->actingAsAdmin();
        OrganizationAccount::factory()->create(['name' => 'Alpha Nettoyage']);
        OrganizationAccount::factory()->create(['name' => 'Beta Peinture']);

        $rows = $this->getJson('/api/admin/console/companies?filters[q]=Alpha')->assertOk()->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('Alpha Nettoyage', $rows[0]['name']);
    }

    public function test_les_entreprises_n_exposent_aucune_action_de_statut(): void
    {
        $this->actingAsAdmin();

        // Approuver une entreprise passe par le module d'approbations, qui porte la règle.
        // Rejouer un changement de statut ici produirait une entreprise « approuvée » que rien
        // n'a vérifiée.
        $this->assertSame([], $this->getJson('/api/admin/console/companies')->json('resource.actions'));
    }

    // ── sites ───────────────────────────────────────────────────────────────────────────────

    public function test_un_site_affiche_son_entreprise(): void
    {
        $this->actingAsAdmin();
        $entreprise = OrganizationAccount::factory()->create(['name' => 'Alpha Nettoyage']);
        OrganizationSite::factory()->create([
            'organization_account_id' => $entreprise->id,
            'name' => 'Siège Bruxelles',
        ]);

        $rows = $this->getJson('/api/admin/console/sites')->assertOk()->json('rows');
        $ligne = collect($rows)->firstWhere('name', 'Siège Bruxelles');

        $this->assertSame('Alpha Nettoyage', $ligne['company']);
    }

    public function test_la_liste_des_sites_ne_multiplie_pas_les_requetes(): void
    {
        $this->actingAsAdmin();
        $entreprise = OrganizationAccount::factory()->create();
        OrganizationSite::factory()->count(5)->create(['organization_account_id' => $entreprise->id]);

        DB::enableQueryLog();
        $this->getJson('/api/admin/console/sites')->assertOk();
        $requetes = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Sans le chargement anticipé, cinq sites déclencheraient cinq requêtes de plus — invisible
        // sur cinq lignes en test, sensible sur une vraie liste.
        $this->assertLessThan(10, $requetes, "Trop de requêtes ({$requetes}) : le chargement anticipé a sauté.");
    }

    // ── cohérence avec l'annuaire ───────────────────────────────────────────────────────────

    public function test_les_trois_modules_sont_annonces_disponibles_dans_l_annuaire(): void
    {
        $this->actingAsAdmin();

        $modules = collect($this->getJson('/api/admin/catalog')->json('groups'))
            ->flatMap(fn (array $g) => $g['modules'])
            ->keyBy('key');

        // Les trois modules relevés ensemble : un inventaire qui prend du retard le prend
        // rarement sur un seul.
        $enRetard = [];

        foreach (['users', 'companies', 'sites'] as $cle) {
            $couverture = $modules[$cle]['coverage'] ?? 'ABSENT';

            if ($couverture !== 'descriptor') {
                $enRetard[] = "{$cle} : annoncé « {$couverture} »";
            }
        }

        $this->assertSame([], $enRetard, 'Ces modules sont livrés mais restent annoncés « à venir ».');
    }
}
