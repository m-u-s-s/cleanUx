<?php

namespace Tests\Feature\Admin;

use App\Admin\Console\ResourceRegistry;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les quatre domaines du catalogue se PILOTENT depuis le mobile, pas seulement se consultent.
 *
 * CE QUE ÇA COMBLE. Les écrans mobiles montraient des listes et rien d'autre : ni ajout, ni
 * modification, ni suppression. Le moteur de console possédait pourtant déjà les écrans de
 * formulaire — ce qui manquait, c'était que les descripteurs déclarent QUOI éditer. Quatre
 * descripteurs sans un seul champ, et l'application n'avait aucune prise sur le catalogue.
 *
 * LA SUPPRESSION DEMANDE UNE GARDE PROPRE. Le `destroy` du moteur supprimait sans rien consulter :
 * un pays effacé aurait emporté ses zones, et avec elles l'historique de facturation qui s'y
 * rattache. Le web refuse en disant pourquoi ; l'API doit refuser de la même façon, sinon la règle
 * dépend de la porte par laquelle on entre.
 */
class CatalogDescriptorsEditableTest extends TestCase
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

    /** @return list<array{0: string}> */
    public static function domainesDuCatalogue(): array
    {
        return [
            'pays' => ['countries'],
            'zones' => ['zones'],
            'métiers' => ['trades'],
            'secteurs' => ['catalog'],
        ];
    }

    /**
     * @dataProvider domainesDuCatalogue
     */
    public function test_chaque_domaine_declare_de_quoi_le_modifier(string $cle): void
    {
        $descripteur = app(ResourceRegistry::class)->for($cle);

        $this->assertNotNull($descripteur, "descripteur {$cle} introuvable");

        // Sans champ, l'écran de formulaire du mobile s'ouvre sur du vide : l'application a l'air
        // de savoir modifier, et ne le sait pas.
        $this->assertNotEmpty(
            $descripteur->formFields(),
            "Le domaine {$cle} ne déclare aucun champ : le mobile ne peut ni créer ni modifier.",
        );
    }

    /**
     * @dataProvider domainesDuCatalogue
     */
    public function test_chaque_champ_declare_ses_regles(string $cle): void
    {
        $sansRegle = [];

        foreach (app(ResourceRegistry::class)->for($cle)->formFields() as $champ) {
            // `toArray()` sert l'interface et n'expose que `required` ; les règles se lisent par
            // `validationRules()`, qui est ce que le contrôleur donne au validateur.
            if ($champ->validationRules() === []) {
                $sansRegle[] = $champ->key();
            }
        }

        // Un champ sans règle accepte n'importe quoi : la validation du modèle n'existe pas ici,
        // c'est le descripteur qui la porte.
        $this->assertSame([], $sansRegle, "Champs sans règle sur {$cle}.");
    }

    public function test_il_cree_un_pays(): void
    {
        $this->postJson('/api/admin/console/countries', [
            'iso_code' => 'FR',
            'name' => 'France',
            'currency_code' => 'EUR',
        ])->assertCreated();

        $this->assertDatabaseHas('countries', ['iso_code' => 'FR', 'name' => 'France']);
    }

    public function test_il_modifie_un_pays(): void
    {
        $pays = Country::factory()->create(['name' => 'Belgique']);

        $this->patchJson("/api/admin/console/countries/{$pays->id}", ['name' => 'Royaume de Belgique'])
            ->assertOk();

        $this->assertSame('Royaume de Belgique', $pays->fresh()->name);
    }

    public function test_il_bascule_l_activation_d_un_pays_sans_toucher_aux_zones(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create([
            'country_id' => $pays->id,
            'is_bookable' => true,
            'status' => 'active',
        ]);

        $this->postJson("/api/admin/console/countries/{$pays->id}/actions/toggle-active")->assertOk();

        // La règle du web : éteindre un pays est une LECTURE côté zones. Les rallumer ou les
        // éteindre ici ferait perdre celles qui étaient fermées pour leur propre raison.
        $this->assertFalse((bool) $pays->fresh()->is_active);
        $this->assertTrue((bool) $zone->fresh()->is_bookable);
        $this->assertSame('active', $zone->fresh()->status);
    }

    public function test_il_refuse_de_supprimer_un_pays_qui_porte_des_zones(): void
    {
        $pays = Country::factory()->create();
        ServiceZone::factory()->count(3)->create(['country_id' => $pays->id]);

        $reponse = $this->deleteJson("/api/admin/console/countries/{$pays->id}")->assertStatus(409);

        // Le refus doit porter la RAISON, avec le compte : sur un téléphone, personne n'ira lire
        // la base pour comprendre ce qui bloque.
        $this->assertStringContainsString('3', implode(' ', $reponse->json('reasons') ?? []));
        $this->assertDatabaseHas('countries', ['id' => $pays->id]);
    }

    public function test_il_supprime_un_pays_sans_rien_dessous(): void
    {
        $pays = Country::factory()->create();

        $this->deleteJson("/api/admin/console/countries/{$pays->id}")->assertOk();

        $this->assertDatabaseMissing('countries', ['id' => $pays->id]);
    }

    public function test_il_cree_une_zone_dans_un_pays(): void
    {
        $pays = Country::factory()->create();

        $this->postJson('/api/admin/console/zones', [
            'country_id' => $pays->id,
            'name' => 'Anvers',
            'code' => 'ANV',
        ])->assertCreated();

        $this->assertDatabaseHas('service_zones', ['name' => 'Anvers', 'country_id' => $pays->id]);
    }

    public function test_une_zone_creee_par_l_api_nait_fermee(): void
    {
        $pays = Country::factory()->create();

        $this->postJson('/api/admin/console/zones', [
            'country_id' => $pays->id,
            'name' => 'Anvers',
            'code' => 'ANV',
        ])->assertCreated();

        // Même règle que le web : créer une zone ne la rend pas commandable avant qu'on ait réglé
        // son catalogue et ses prix.
        $zone = ServiceZone::where('name', 'Anvers')->firstOrFail();
        $this->assertFalse((bool) $zone->is_bookable);
    }

    public function test_il_refuse_de_supprimer_une_zone_qui_porte_des_reservations(): void
    {
        $zone = ServiceZone::factory()->create();
        Booking::factory()->create(['service_zone_id' => $zone->id]);

        $this->deleteJson("/api/admin/console/zones/{$zone->id}")->assertStatus(409);

        $this->assertDatabaseHas('service_zones', ['id' => $zone->id]);
    }

    public function test_il_ouvre_et_ferme_une_zone_aux_reservations(): void
    {
        $zone = ServiceZone::factory()->create(['is_bookable' => true]);

        $this->postJson("/api/admin/console/zones/{$zone->id}/actions/toggle-bookable")->assertOk();

        $this->assertFalse((bool) $zone->fresh()->is_bookable);
    }

    public function test_il_cree_un_metier(): void
    {
        $secteur = Sector::query()->firstOrFail();

        $this->postJson('/api/admin/console/trades', [
            'name' => 'Ramonage',
            'slug' => 'ramonage',
            'code' => 'RAMONAGE',
            'sector_id' => $secteur->id,
        ])->assertCreated();

        $this->assertDatabaseHas('trades', ['slug' => 'ramonage', 'sector_id' => $secteur->id]);
    }

    public function test_il_bascule_l_activation_d_un_metier(): void
    {
        $metier = Trade::query()->firstOrFail();
        $avant = (bool) $metier->is_active;

        $this->postJson("/api/admin/console/trades/{$metier->id}/actions/toggle-active")->assertOk();

        $this->assertSame(! $avant, (bool) $metier->fresh()->is_active);
    }

    public function test_il_cree_un_secteur(): void
    {
        $this->postJson('/api/admin/console/catalog', [
            'name' => 'Mobilité',
            'slug' => 'mobilite',
        ])->assertCreated();

        $this->assertDatabaseHas('sectors', ['slug' => 'mobilite']);
    }

    public function test_un_lecteur_seul_ne_cree_rien(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]), ['*']);

        $this->postJson('/api/admin/console/countries', [
            'iso_code' => 'FR', 'name' => 'France', 'currency_code' => 'EUR',
        ])->assertForbidden();

        $this->assertDatabaseMissing('countries', ['iso_code' => 'FR']);
    }
}
