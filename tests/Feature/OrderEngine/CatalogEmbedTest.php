<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le catalogue, servi dans l'application mobile, sans le chrome du site. */
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
        // LES CAPACITES SONT ACCORDEES, et c'est ce que `EnforceModuleGate` exige desormais.
        return User::factory()->adminComplet()->create();
    }
}
