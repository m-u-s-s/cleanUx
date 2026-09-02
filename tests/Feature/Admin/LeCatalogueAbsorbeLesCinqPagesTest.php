<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrderEngine\CountryCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LE CATALOGUE ABSORBE LES CINQ PAGES QUI L'ENTOURAIENT.
 *
 * Sa colonne vertebrale ne bouge pas — Pays, puis Zones, puis Metiers et prix — parce que c'est
 * elle qui rend la creation d'une mission simple. Les cinq autres pages deviennent des onglets a
 * son premier niveau, pas des detours.
 */
class LeCatalogueAbsorbeLesCinqPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function urlsFusionnees(): array
    {
        return [
            'zones' => ['/admin/zones', '/admin/catalogue?onglet=zones'],
            'metiers' => ['/admin/trades', '/admin/catalogue?onglet=metiers'],
            'services' => ['/admin/services', '/admin/catalogue?onglet=services'],
            'marche' => ['/admin/international', '/admin/catalogue?onglet=marche'],
            // Vrai doublon : il editait les memes colonnes, sans la garde devise/pays.
            'pays' => ['/admin/countries', '/admin/catalogue'],
        ];
    }

    #[DataProvider('urlsFusionnees')]
    public function test_chaque_ancienne_url_conduit_au_catalogue(string $url, string $cible): void
    {
        $this->actingAs($this->admin())
            ->get($url)
            ->assertRedirect($cible);
    }

    /** TEMOIN — le catalogue repond, sans quoi les redirections ci-dessus ne prouveraient rien. */
    public function test_temoin_le_catalogue_repond(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/catalogue')
            ->assertOk();
    }

    public function test_le_catalogue_porte_les_cinq_onglets(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CountryCenter::class)
            ->assertSee('Pays')
            ->assertSee('Zones')
            ->assertSee('Métiers')
            ->assertSee('Services')
            ->assertSee('Marché');
    }

    /** L'ENTREE RESTE LA MEME : le premier onglet est le catalogue, pas un menu. */
    public function test_l_onglet_par_defaut_reste_les_pays(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CountryCenter::class)
            ->assertSet('onglet', 'pays');
    }

    /** La page fusionnee n'a plus de composant propre : seule sa route survit, en redirection. */
    public function test_le_composant_du_doublon_a_disparu(): void
    {
        // Sur le FICHIER, pas sur `class_exists` : celui-ci declenche l'autoload, et un classmap
        // encore chaud le trouverait la ou le disque ne l'a plus.
        $this->assertFileDoesNotExist(
            app_path('Livewire/Admin/CountryOperationsCenter.php'),
            'Le doublon « Pilotage des pays » est encore la.'
        );

        // TEMOIN — la route, elle, existe toujours pour ne casser aucun signet.
        $this->assertTrue(Route::has('admin.countries'));
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
