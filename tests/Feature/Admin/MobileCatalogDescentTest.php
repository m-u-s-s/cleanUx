<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La descente Pays → Zones → Métiers, servie à l'application mobile.
 *
 * POURQUOI CES ENDPOINTS PLUTÔT QUE LA LISTE GÉNÉRIQUE. Le moteur de console sait rendre n'importe
 * quel domaine décrit par un descripteur, et il servait jusqu'ici une liste PLATE de secteurs pour
 * ce module. Le web est devenu une descente : le mobile qui montrerait autre chose ferait croire à
 * deux catalogues différents, et c'est l'écran de terrain qu'on croirait.
 *
 * CE QUI EST RÉUTILISÉ : les descripteurs `countries` et `zones`, déjà en place. Ce qui manquait,
 * c'est le CLOISONNEMENT des zones par pays et la vue « métiers de cette zone » — l'état
 * d'ouverture n'appartenant ni au métier ni à la zone, mais à leur couple.
 */
class MobileCatalogDescentTest extends TestCase
{
    use RefreshDatabase;

    private Country $belgique;

    private Country $france;

    private ServiceZone $bruxelles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);

        $this->belgique = Country::factory()->create(['name' => 'Belgique']);
        $this->france = Country::factory()->create(['name' => 'France']);
        $this->bruxelles = ServiceZone::factory()->create([
            'country_id' => $this->belgique->id,
            'name' => 'Bruxelles',
        ]);
        ServiceZone::factory()->create(['country_id' => $this->france->id, 'name' => 'Paris']);

        Sanctum::actingAs(User::factory()->adminComplet()->create(), ['*']);
    }

    public function test_les_zones_se_filtrent_par_pays(): void
    {
        $reponse = $this->getJson('/api/admin/console/zones?filters[country_id]='.$this->belgique->id)
            ->assertOk();

        $noms = collect($reponse->json('rows'))->pluck('name')->all();

        // Sans ce filtre, l'écran mobile des zones belges afficherait Paris — et le cloisonnement
        // devrait se faire côté client, où il ne protège rien.
        $this->assertContains('Bruxelles', $noms);
        $this->assertNotContains('Paris', $noms);
    }

    public function test_un_filtre_pays_inconnu_ne_vide_pas_la_liste_en_silence(): void
    {
        // Un filtre non déclaré est ignoré par le moteur. On vérifie que `country_id` EST déclaré,
        // faute de quoi la requête ci-dessus rendrait toutes les zones sans rien signaler.
        $reponse = $this->getJson('/api/admin/console/zones')->assertOk();

        $this->assertContains(
            'country_id',
            collect($reponse->json('resource.filters'))->pluck('key')->all(),
        );
    }

    public function test_il_sert_les_metiers_d_une_zone_avec_leur_etat(): void
    {
        $metier = Trade::query()->firstOrFail();

        TradeZonePricing::create([
            'trade_id' => $metier->id,
            'service_zone_id' => $this->bruxelles->id,
            'base_rate_cents' => 4500,
            'is_active' => true,
        ]);

        $reponse = $this->getJson("/api/admin/catalogue/zones/{$this->bruxelles->id}/trades")->assertOk();

        $ligne = collect($reponse->json('data'))->firstWhere('id', $metier->id);

        $this->assertNotNull($ligne, 'le métier doit apparaître dans le catalogue de la zone');
        $this->assertTrue($ligne['is_open']);
        $this->assertSame(4500, $ligne['base_rate_cents']);
    }

    public function test_un_metier_sans_ligne_est_annonce_ferme(): void
    {
        $metier = Trade::query()->firstOrFail();

        $reponse = $this->getJson("/api/admin/catalogue/zones/{$this->bruxelles->id}/trades")->assertOk();
        $ligne = collect($reponse->json('data'))->firstWhere('id', $metier->id);

        // L'absence de ligne est un ÉTAT, pas un trou : « fermé » se dit, il ne se déduit pas d'un
        // métier manquant dans la réponse.
        $this->assertFalse($ligne['is_open']);
    }

    public function test_les_metiers_sont_groupes_par_secteur(): void
    {
        $reponse = $this->getJson("/api/admin/catalogue/zones/{$this->bruxelles->id}/trades")->assertOk();

        $secteurs = collect($reponse->json('data'))->pluck('sector')->filter()->unique();

        // Le carrousel client est ordonné par secteur : un écran qui l'ignore ne permet pas de
        // vérifier ce que verra le client.
        $this->assertTrue($secteurs->isNotEmpty());
        $this->assertContains($secteurs->first(), Sector::pluck('name')->all());
    }

    public function test_il_bascule_l_ouverture_d_un_metier(): void
    {
        $metier = Trade::query()->firstOrFail();

        $this->postJson("/api/admin/catalogue/zones/{$this->bruxelles->id}/trades/{$metier->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_open', true);

        $this->assertDatabaseHas('trade_zone_pricing', [
            'trade_id' => $metier->id,
            'service_zone_id' => $this->bruxelles->id,
            'is_active' => true,
        ]);
    }

    public function test_la_bascule_conserve_la_grille(): void
    {
        $metier = Trade::query()->firstOrFail();

        TradeZonePricing::create([
            'trade_id' => $metier->id,
            'service_zone_id' => $this->bruxelles->id,
            'base_rate_cents' => 7700,
            'is_active' => true,
        ]);

        $this->postJson("/api/admin/catalogue/zones/{$this->bruxelles->id}/trades/{$metier->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_open', false);

        // Même règle que sur le web : éteindre n'efface pas le tarif, sinon rallumer repartirait
        // de zéro et il faudrait refaire une négociation.
        $this->assertSame(
            7700,
            (int) TradeZonePricing::where('trade_id', $metier->id)
                ->where('service_zone_id', $this->bruxelles->id)
                ->value('base_rate_cents'),
        );
    }

    public function test_un_lecteur_seul_ne_bascule_rien(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]), ['*']);

        $metier = Trade::query()->firstOrFail();

        $avant = TradeZonePricing::query()
            ->where('trade_id', $metier->id)
            ->where('service_zone_id', $this->bruxelles->id)
            ->value('is_active');

        $this->postJson("/api/admin/catalogue/zones/{$this->bruxelles->id}/trades/{$metier->id}/toggle")
            ->assertForbidden();

        /*
         * La règle qui protège le web doit protéger l'API : c'est la même décision, et un compte
         * en lecture seule passe la garde « est-ce un administrateur ».
         *
         * On vérifie l'ÉTAT INCHANGÉ plutôt qu'une table vide : le catalogue est désormais semé
         * avec sa grille complète (métier × zone), sans quoi aucun métier ne serait vendu nulle
         * part. Compter les lignes ne dirait plus rien du refus.
         */
        $this->assertSame(
            $avant,
            TradeZonePricing::query()
                ->where('trade_id', $metier->id)
                ->where('service_zone_id', $this->bruxelles->id)
                ->value('is_active'),
        );
    }

    public function test_les_pays_se_trient_par_nom(): void
    {
        /*
         * LE BUG QUE CE TEST FIXE. `sorts()` est une liste BLANCHE, et elle valait `['id']` seule.
         * L'écran mobile demandait `sort=name`, l'API répondait 422, et l'application affichait
         * « Impossible de charger les pays » — un message d'erreur générique pour un tri refusé.
         *
         * Un pays trié par identifiant, c'est l'ordre de création : illisible dès qu'il y en a
         * cinq. Le tri par nom devait donc être PERMIS, pas retiré de l'écran.
         */
        $reponse = $this->getJson('/api/admin/console/countries?sort=name&direction=asc')->assertOk();

        $noms = collect($reponse->json('rows'))->pluck('name')->all();
        $attendu = $noms;
        sort($attendu);

        $this->assertSame($attendu, $noms);
    }

    public function test_les_zones_se_trient_par_nom(): void
    {
        $this->getJson('/api/admin/console/zones?sort=name&direction=asc')->assertOk();
    }

    public function test_un_tri_inconnu_reste_refuse(): void
    {
        // La liste blanche garde son rôle : on l'a élargie, pas supprimée.
        $this->getJson('/api/admin/console/countries?sort=mot_de_passe')->assertStatus(422);
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']), ['*']);

        $this->getJson("/api/admin/catalogue/zones/{$this->bruxelles->id}/trades")->assertForbidden();
    }
}
