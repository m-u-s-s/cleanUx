<?php

namespace Tests\Feature;

use App\Livewire\Admin\OrderEngine\CountryCenter;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA PAGE « PILOTAGE DES PAYS » A FUSIONNE DANS LE CATALOGUE.
 *
 * Elle editait les memes colonnes de `countries` que `/admin/catalogue`, en moins sur : le
 * catalogue refuse une devise qui ne correspond pas au pays, elle non. Son seul apport,
 * `iso3_code`, y a ete porte — et c'est ce que ce fichier atteste desormais.
 */
class AdminCountriesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ancienne_url_conduit_au_catalogue(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.countries'))
            ->assertRedirect('/admin/catalogue');
    }

    public function test_le_catalogue_edite_le_pays_et_son_code_iso3(): void
    {
        $pays = $this->belgique();

        Livewire::actingAs($this->admin())
            ->test(CountryCenter::class)
            ->call('editer', $pays->id)
            ->set('formulaire.name', 'Belgique Opérations')
            ->set('formulaire.iso3_code', 'bel')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('countries', [
            'id' => $pays->id,
            'name' => 'Belgique Opérations',
            // Saisi en minuscules, enregistre en majuscules : le catalogue normalise.
            'iso3_code' => 'BEL',
        ]);
    }

    /** L'activation, elle aussi, se fait depuis le catalogue. */
    public function test_le_catalogue_bascule_l_activation_du_pays(): void
    {
        $pays = $this->belgique();

        Livewire::actingAs($this->admin())
            ->test(CountryCenter::class)
            ->call('basculerActivation', $pays->id);

        $this->assertFalse((bool) $pays->fresh()->is_active);
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }

    private function belgique(): Country
    {
        return Country::create([
            'iso_code' => 'BE',
            'iso3_code' => 'BEL',
            'name' => 'Belgique',
            'official_name' => 'Royaume de Belgique',
            'default_locale' => 'fr_BE',
            'currency_code' => 'EUR',
            'phone_code' => '+32',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);
    }
}
