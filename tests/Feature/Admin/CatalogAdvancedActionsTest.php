<?php

namespace Tests\Feature\Admin;

use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les gestes du catalogue que le web sait faire et que l'API ne servait pas.
 *
 * RÉORDONNER N'EST PAS COSMÉTIQUE. L'ordre des secteurs est celui du CARROUSEL, l'ordre des métiers
 * celui du dock : le premier secteur est ce que voit tout visiteur, le premier métier ce qu'on lui
 * propose. Un mobile qui ne sait pas le régler laisse cette décision au seul poste de travail.
 *
 * ARCHIVER N'EST PAS SUPPRIMER. `CatalogArchiver` conserve la ligne et ses métiers ; l'API doit
 * passer par lui plutôt que d'inventer un `delete`, sinon deux chemins vers la même table
 * produiraient deux résultats différents selon la porte empruntée.
 */
class CatalogAdvancedActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);

        Sanctum::actingAs(User::factory()->adminComplet()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]), ['*']);
    }

    public function test_il_monte_un_secteur_dans_le_carrousel(): void
    {
        $ordre = Sector::query()->orderBy('sort_order')->pluck('id')->all();
        $second = $ordre[1];

        $this->postJson("/api/admin/console/catalog/{$second}/actions/move-up")->assertOk();

        $apres = Sector::query()->orderBy('sort_order')->pluck('id')->all();

        // Il prend la place du premier : c'est ce que voit tout visiteur en arrivant.
        $this->assertSame($second, $apres[0]);
    }

    public function test_il_descend_un_secteur(): void
    {
        $ordre = Sector::query()->orderBy('sort_order')->pluck('id')->all();
        $premier = $ordre[0];

        $this->postJson("/api/admin/console/catalog/{$premier}/actions/move-down")->assertOk();

        $this->assertSame($premier, Sector::query()->orderBy('sort_order')->pluck('id')->all()[1]);
    }

    public function test_monter_le_premier_ne_fait_rien(): void
    {
        $ordre = Sector::query()->orderBy('sort_order')->pluck('id')->all();

        $this->postJson("/api/admin/console/catalog/{$ordre[0]}/actions/move-up")->assertOk();

        // Aucune erreur, aucun mouvement : un bouton au bord ne doit ni casser ni surprendre.
        $this->assertSame($ordre, Sector::query()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_il_archive_un_secteur_sans_toucher_a_ses_metiers(): void
    {
        $secteur = Sector::query()->has('trades')->firstOrFail();
        $metiers = $secteur->trades()->pluck('id')->all();

        $this->postJson("/api/admin/console/catalog/{$secteur->id}/actions/archive")->assertOk();

        // « Ses métiers restent intacts » — la promesse que fait l'écran web, mot pour mot.
        foreach ($metiers as $id) {
            $this->assertNotNull(Trade::find($id), "le métier {$id} a disparu avec son secteur");
        }
    }

    public function test_il_deplace_un_metier_dans_son_secteur(): void
    {
        // `having` sans `group by` est refusé par SQLite, sur lequel tourne la suite : on filtre
        // en PHP plutôt que d'écrire une requête qui ne passerait qu'en MySQL.
        $secteur = Sector::query()->withCount('trades')->get()
            ->first(fn (Sector $s) => (int) $s->trades_count >= 2);

        $this->assertNotNull($secteur, 'il faut un secteur d’au moins deux métiers pour ce test');
        $ordre = $secteur->trades()->orderBy('sort_order')->pluck('id')->all();

        $this->postJson("/api/admin/console/trades/{$ordre[1]}/actions/move-up")->assertOk();

        $this->assertSame($ordre[1], $secteur->trades()->orderBy('sort_order')->pluck('id')->all()[0]);
    }

    public function test_il_rattache_un_metier_orphelin_a_un_secteur(): void
    {
        $orphelin = Trade::query()->create([
            'name' => 'Orphelin', 'slug' => 'orphelin', 'code' => 'ORPHELIN', 'is_active' => true,
        ]);
        $secteur = Sector::query()->firstOrFail();

        $this->postJson("/api/admin/console/trades/{$orphelin->id}/actions/attach-sector", [
            'sector_id' => $secteur->id,
        ])->assertOk();

        // Rattacher est ce qui fait ENTRER un métier dans le parcours client : sans secteur, il
        // n'apparaît nulle part, et rien ne le signale.
        $this->assertSame($secteur->id, $orphelin->fresh()->sector_id);
    }

    public function test_le_metier_rattache_arrive_en_fin_de_secteur(): void
    {
        $orphelin = Trade::query()->create([
            'name' => 'Orphelin', 'slug' => 'orphelin', 'code' => 'ORPHELIN', 'is_active' => true,
        ]);
        $secteur = Sector::query()->has('trades')->firstOrFail();

        $this->postJson("/api/admin/console/trades/{$orphelin->id}/actions/attach-sector", [
            'sector_id' => $secteur->id,
        ])->assertOk();

        // En fin de liste et non en tête : un métier qu'on vient de rattacher n'a pas à passer
        // devant ceux qui se vendent déjà.
        $dernier = $secteur->trades()->orderBy('sort_order')->pluck('id')->last();
        $this->assertSame($orphelin->id, $dernier);
    }

    public function test_rattacher_exige_un_secteur_existant(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->postJson("/api/admin/console/trades/{$metier->id}/actions/attach-sector", [
            'sector_id' => 999999,
        ])->assertStatus(422);
    }

    public function test_un_lecteur_seul_ne_reordonne_rien(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]), ['*']);

        $ordre = Sector::query()->orderBy('sort_order')->pluck('id')->all();

        $this->postJson("/api/admin/console/catalog/{$ordre[1]}/actions/move-up")->assertForbidden();

        $this->assertSame($ordre, Sector::query()->orderBy('sort_order')->pluck('id')->all());
    }
}
