<?php

namespace Tests\Feature\OrderEngine;

use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le moteur de commande, atteignable depuis l'application.
 *
 * Il n'existait QUE sur le web : aucun écran natif, aucun point d'API — `routes/api/` n'en parle
 * nulle part — et aucune entrée dans le registre de parité, pas même en vue embarquée. Un client
 * sur l'application réservait encore par catégorie de service, sans secteur, sans question propre au
 * métier, sans mode immédiat et sans devis explicable ligne par ligne. Le onzième critère
 * d'acceptation était donc faux sur mobile.
 *
 * L'entrée en vue embarquée est le chemin le moins coûteux vers la parité : le parcours complet
 * devient joignable depuis l'application, et la migration vers un écran natif se fera plus tard en
 * basculant `mobile` sur `native` — sans autre changement de code, c'est la promesse du registre.
 */
class MobileParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_the_order_journey_is_registered_for_mobile(): void
    {
        $module = collect(config('parity.modules'))->firstWhere('key', 'order-journey');

        $this->assertNotNull($module, 'Le moteur de commande n’existe pas dans le registre de parité.');
        $this->assertSame('/commander', $module['path']);
        $this->assertSame('webview', $module['mobile']);
        $this->assertContains('client', $module['roles']);
    }

    /** Un client connecté le voit dans sa carte de parité — c'est elle que l'application lit. */
    public function test_a_client_sees_it_in_their_parity_map(): void
    {
        $response = $this->actingAs(User::factory()->client()->create())
            ->getJson('/api/parity-map');

        $response->assertOk();

        $this->assertContains(
            'order-journey',
            collect($response->json('data'))->pluck('key')->all(),
            'La carte de parité du client ne propose pas le moteur de commande.',
        );
    }

    /**
     * Le parcours se rend SANS le chrome du site quand il est embarqué.
     *
     * Une vue embarquée qui garde l'en-tête, la navigation basse et le pied de page du site donne
     * une application dans laquelle on navigue deux fois — et sur laquelle la barre du pouce du
     * parcours se retrouve masquée par celle du site.
     */
    public function test_the_journey_renders_without_site_chrome_when_embedded(): void
    {
        $plain = $this->get('/commander');
        $embedded = $this->get('/commander?embed=1');

        $plain->assertOk();
        $embedded->assertOk();

        $this->assertLessThan(
            mb_strlen($plain->getContent()),
            mb_strlen($embedded->getContent()),
            'La vue embarquée rend autant que la page complète : le chrome du site n’a pas été retiré.',
        );
    }

    /**
     * Le parcours reste PUBLIC, y compris embarqué.
     *
     * C'est la première loi : le prix se voit avant l'identité. Une entrée de registre réservée aux
     * clients connectés ne doit pas transformer la route en route authentifiée — le visiteur du
     * site web garde son estimation sans compte.
     */
    public function test_registering_it_for_mobile_does_not_close_the_public_route(): void
    {
        $this->get('/commander')->assertOk();
        $this->get('/commander/recapitulatif')->assertOk();
    }
}
