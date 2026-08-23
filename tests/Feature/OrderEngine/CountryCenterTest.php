<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CountryCenter;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Le premier niveau de la descente : les pays. */
class CountryCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_il_liste_les_pays(): void
    {
        Country::factory()->create(['name' => 'Belgique']);
        Country::factory()->create(['name' => 'France']);

        Livewire::test(CountryCenter::class)
            ->assertOk()
            ->assertSee('Belgique')
            ->assertSee('France');
    }

    public function test_il_ajoute_un_pays(): void
    {
        Livewire::test(CountryCenter::class)
            ->call('nouveau')
            ->set('formulaire.name', 'France')
            ->set('formulaire.iso_code', 'FR')
            ->set('formulaire.currency_code', 'EUR')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('countries', ['iso_code' => 'FR', 'name' => 'France']);
    }

    public function test_un_pays_neuf_nait_inactif(): void
    {
        Livewire::test(CountryCenter::class)
            ->call('nouveau')
            ->set('formulaire.name', 'France')
            ->set('formulaire.iso_code', 'FR')
            ->set('formulaire.currency_code', 'EUR')
            ->call('enregistrer');

        // Une faute de frappe ne doit pas rendre un marché commandable. L'ouverture est un geste
        // séparé et délibéré.
        $pays = Country::where('iso_code', 'FR')->firstOrFail();
        $this->assertFalse((bool) $pays->is_active);
    }

    public function test_il_refuse_deux_pays_au_meme_code_iso(): void
    {
        Country::factory()->create(['iso_code' => 'FR']);

        Livewire::test(CountryCenter::class)
            ->call('nouveau')
            ->set('formulaire.name', 'France bis')
            ->set('formulaire.iso_code', 'FR')
            ->set('formulaire.currency_code', 'EUR')
            ->call('enregistrer')
            ->assertHasErrors(['formulaire.iso_code']);
    }

    public function test_il_modifie_un_pays_sans_se_plaindre_de_son_propre_code(): void
    {
        $pays = Country::factory()->create(['iso_code' => 'BE', 'name' => 'Belgique']);

        Livewire::test(CountryCenter::class)
            ->call('editer', $pays->id)
            ->set('formulaire.name', 'Royaume de Belgique')
            ->call('enregistrer')
            ->assertHasNoErrors();

        // La règle d'unicité doit s'ignorer elle-même en édition, sinon aucun pays n'est
        // modifiable une fois créé.
        $this->assertSame('Royaume de Belgique', $pays->fresh()->name);
    }

    public function test_il_bascule_l_activation_sans_toucher_aux_zones(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create([
            'country_id' => $pays->id,
            'is_bookable' => true,
            'status' => 'active',
        ]);

        Livewire::test(CountryCenter::class)->call('basculerActivation', $pays->id);

        $this->assertFalse((bool) $pays->fresh()->is_active);
        // La règle du chantier : éteindre un pays est une lecture. Les zones ne bougent pas.
        $this->assertTrue((bool) $zone->fresh()->is_bookable);
        $this->assertSame('active', $zone->fresh()->status);
    }

    public function test_il_refuse_de_supprimer_un_pays_qui_porte_des_zones(): void
    {
        $pays = Country::factory()->create();
        ServiceZone::factory()->create(['country_id' => $pays->id]);

        Livewire::test(CountryCenter::class)
            ->call('supprimer', $pays->id)
            ->assertSee('zone(s) rattachée(s)');

        $this->assertDatabaseHas('countries', ['id' => $pays->id]);
    }

    public function test_il_supprime_un_pays_sans_rien_dessous(): void
    {
        $pays = Country::factory()->create();

        Livewire::test(CountryCenter::class)->call('supprimer', $pays->id);

        $this->assertDatabaseMissing('countries', ['id' => $pays->id]);
    }

    public function test_il_affiche_le_nombre_de_zones_de_chaque_pays(): void
    {
        $pays = Country::factory()->create(['name' => 'Belgique']);
        ServiceZone::factory()->count(4)->create(['country_id' => $pays->id]);

        // Sans ce compte, il faut ouvrir chaque pays pour savoir lequel est réellement en service.
        Livewire::test(CountryCenter::class)->assertSee('4');
    }

    public function test_un_non_admin_n_entre_pas(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']));

        // Le refus vaut au niveau du composant, pas seulement de la route : un composant Livewire
        // se monte aussi par requête directe.
        Livewire::test(CountryCenter::class)->assertForbidden();
    }
}
