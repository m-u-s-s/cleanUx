<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le catalogue, servi dans l'application mobile, sans le chrome du site.
 *
 * Le balayage de QA visuelle l'a pris en défaut : les cinq critères de mise en page passaient à
 * 390 px, et la navigation principale du site restait affichée à l'intérieur de la vue embarquée.
 * On navigue alors deux fois — une barre pour l'application, une pour le site — et la barre du site
 * mange la hauteur d'un écran déjà étroit.
 *
 * Le test de rendu embarqué existant ne l'avait pas vu : il vérifie que la page RÉPOND, pas qu'elle
 * a retiré son chrome.
 */
class CatalogEmbedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_the_catalog_drops_the_site_navigation_when_embedded(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/catalogue?embed=1')
            ->assertOk()
            ->assertDontSee('data-chrome="primary-nav"', false);
    }

    /** Et le constructeur, qui s'ouvre depuis lui. */
    public function test_the_builder_drops_it_too(): void
    {
        $this->actingAs($this->admin());
        $trade = Trade::where('slug', 'peinture')->firstOrFail();

        $this->get('/admin/parcours/'.$trade->id.'?embed=1')
            ->assertOk()
            ->assertDontSee('data-chrome="primary-nav"', false);
    }

    /** Hors embarquement, la navigation reste : c'est le site, pas une application. */
    public function test_the_navigation_stays_on_the_web(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/catalogue')
            ->assertOk()
            ->assertSee('data-chrome="primary-nav"', false);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']);
    }
}
