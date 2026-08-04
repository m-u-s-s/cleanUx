<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Créer un métier depuis le catalogue d'une zone.
 *
 * LE TROU QUE ÇA COMBLE. L'écran permettait d'ajouter un secteur et de rattacher un métier
 * EXISTANT, jamais d'en créer un. Il fallait aller sur `/admin/trades`, remplir vingt et un champs,
 * revenir au catalogue rattacher le métier devenu orphelin, puis l'ouvrir dans la zone : trois
 * écrans pour un geste.
 *
 * CE QU'IL FAUT GARDER EN TÊTE EN LISANT CES TESTS. Un métier est un objet GLOBAL — créé une fois,
 * partagé par tous les pays et toutes les zones. Seule son OUVERTURE est propre à la zone. Le créer
 * depuis un écran de zone est commode, mais ne doit pas laisser croire qu'il n'existe qu'ici.
 */
class CatalogTradeCreationTest extends TestCase
{
    use RefreshDatabase;

    private Country $pays;

    private ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->pays = Country::factory()->create();
        $this->zone = ServiceZone::factory()->create([
            'country_id' => $this->pays->id,
            'name' => 'Bruxelles',
        ]);
    }

    /** @return array{country: Country, zone: ServiceZone} */
    private function contexte(): array
    {
        return ['country' => $this->pays, 'zone' => $this->zone];
    }

    public function test_il_cree_un_metier_avec_le_formulaire_complet(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Ramonage')
            ->set('slug', 'ramonage')
            ->set('code', 'RAMONAGE')
            ->set('requires_certification', true)
            ->set('emergency_multiplier', '1.50')
            ->call('enregistrerMetier')
            ->assertHasNoErrors();

        $metier = Trade::where('slug', 'ramonage')->firstOrFail();

        // Les champs « profonds » doivent survivre au voyage : c'est tout l'intérêt d'avoir voulu
        // le formulaire complet plutôt qu'une création rapide.
        $this->assertSame('Ramonage', $metier->name);
        $this->assertTrue((bool) $metier->requires_certification);
        $this->assertSame('1.50', (string) $metier->emergency_multiplier);
    }

    public function test_le_metier_cree_est_rattache_au_secteur_choisi(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Ramonage')
            ->set('slug', 'ramonage')
            ->set('code', 'RAMONAGE')
            ->call('enregistrerMetier');

        // Sans rattachement, le métier atterrirait dans les orphelins et il faudrait un second
        // geste — exactement le détour qu'on supprime.
        $this->assertSame($secteur->id, Trade::where('slug', 'ramonage')->firstOrFail()->sector_id);
    }

    public function test_le_metier_cree_est_ouvert_dans_la_zone_courante(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Ramonage')
            ->set('slug', 'ramonage')
            ->set('code', 'RAMONAGE')
            ->call('enregistrerMetier');

        // On le crée DEPUIS une zone : le laisser fermé obligerait à un troisième clic pour faire
        // ce qu'on venait manifestement faire.
        $this->assertDatabaseHas('trade_zone_pricing', [
            'trade_id' => Trade::where('slug', 'ramonage')->firstOrFail()->id,
            'service_zone_id' => $this->zone->id,
            'is_active' => true,
        ]);
    }

    public function test_il_n_ouvre_le_metier_que_dans_cette_zone(): void
    {
        $secteur = Sector::query()->firstOrFail();
        $autreZone = ServiceZone::factory()->create(['country_id' => $this->pays->id]);

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Ramonage')
            ->set('slug', 'ramonage')
            ->set('code', 'RAMONAGE')
            ->call('enregistrerMetier');

        // Le métier existe partout, mais il n'est OUVERT qu'ici. C'est toute la distinction que
        // l'écran doit préserver.
        $this->assertDatabaseMissing('trade_zone_pricing', [
            'trade_id' => Trade::where('slug', 'ramonage')->firstOrFail()->id,
            'service_zone_id' => $autreZone->id,
        ]);
    }

    public function test_il_refuse_un_slug_deja_pris(): void
    {
        $secteur = Sector::query()->firstOrFail();
        $existant = Trade::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Doublon')
            ->set('slug', $existant->slug)
            ->set('code', 'DOUBLON-UNIQUE')
            ->call('enregistrerMetier')
            ->assertHasErrors(['slug']);
    }

    public function test_il_refuse_un_schema_de_questionnaire_invalide(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Ramonage')
            ->set('slug', 'ramonage')
            ->set('code', 'RAMONAGE')
            ->set('booking_form_schema_json', '{ceci n’est pas du JSON')
            ->call('enregistrerMetier')
            ->assertHasErrors(['booking_form_schema_json']);

        // Un refus ne doit rien créer à moitié.
        $this->assertDatabaseMissing('trades', ['slug' => 'ramonage']);
    }

    public function test_un_refus_laisse_le_formulaire_ouvert(): void
    {
        $secteur = Sector::query()->firstOrFail();
        $existant = Trade::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Doublon')
            ->set('slug', $existant->slug)
            ->set('code', 'DOUBLON-UNIQUE')
            ->call('enregistrerMetier')
            // Refermer ferait perdre les vingt champs que l'administrateur venait de remplir.
            ->assertSet('creationMetierOuverte', true);
    }

    public function test_le_bouton_de_creation_est_offert_dans_chaque_secteur(): void
    {
        Livewire::test(CatalogCenter::class, $this->contexte())
            ->assertSee('Ajouter un métier');
    }

    public function test_il_dit_que_le_metier_existera_partout(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            // Créer depuis une zone ne doit pas laisser croire que le métier n'existe qu'ici :
            // c'est un objet global, seule son ouverture est locale.
            ->assertSee('existera dans tous les pays');
    }

    public function test_fermer_le_formulaire_vide_les_champs(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Abandonné')
            ->call('fermerCreationMetier')
            ->assertSet('creationMetierOuverte', false)
            ->assertSet('name', '');
    }

    public function test_la_creation_n_ecrit_pas_dans_la_table_condamnee(): void
    {
        $secteur = Sector::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('ouvrirCreationMetier', $secteur->id)
            ->set('name', 'Ramonage')
            ->set('slug', 'ramonage')
            ->set('code', 'RAMONAGE')
            ->call('enregistrerMetier');

        // `trade_zone_settings` reste le doublon condamné, y compris sur ce nouveau chemin.
        $this->assertDatabaseCount('trade_zone_settings', 0);
    }
}
