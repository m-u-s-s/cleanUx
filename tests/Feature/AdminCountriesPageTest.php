<?php

namespace Tests\Feature;

use App\Livewire\Admin\CountryOperationsCenter;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCountriesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_countries_page(): void
    {
        $admin = User::factory()->admin()->create([
            'access_scope' => 'all',
            'permissions' => [],
            'is_active' => true,
        ]);

        Country::create([
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

        $this->actingAs($admin)
            ->get(route('admin.countries'))
            ->assertOk()
            ->assertSee('Pilotage des pays')
            ->assertSee('Liste des pays');
    }

    public function test_country_operations_center_can_update_selected_country(): void
    {
        $admin = User::factory()->admin()->create([
            'access_scope' => 'all',
            'permissions' => [],
            'is_active' => true,
        ]);

        $country = Country::create([
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

        $this->actingAs($admin);

        Livewire::test(CountryOperationsCenter::class)
            ->call('selectCountry', $country->id)
            ->set('name', 'Belgique Opérations')
            ->set('official_name', 'Royaume de Belgique Opérations')
            ->set('default_locale', 'fr_BE')
            ->set('currency_code', 'EUR')
            ->set('phone_code', '+32')
            ->set('timezone', 'Europe/Brussels')
            ->set('iso_code', 'BE')
            ->set('iso3_code', 'BEL')
            ->set('is_active', false)
            ->call('saveCountry')
            ->assertHasNoErrors()
            ->assertSee('Pays enregistré avec succès.');

        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'name' => 'Belgique Opérations',
            'official_name' => 'Royaume de Belgique Opérations',
            'is_active' => false,
        ]);
    }
}
