<?php

namespace Tests\Feature\OrderEngine;

use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le moteur de commande, atteignable depuis l'application. */
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
        $module = collect(config('parity.modules'))->firstWhere('key', 'booking');

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

        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertContains(
            'booking',
            $keys,
            'La carte de parité du client ne propose pas le moteur de commande.',
        );

        // UNE SEULE entrée de réservation.
        $this->assertSame(
            ['/commander'],
            collect($response->json('data'))
                ->filter(fn (array $m) => str_contains((string) $m['path'], 'commander')
                    || str_contains((string) $m['path'], 'prendre-rendez-vous'))
                ->pluck('path')
                ->values()
                ->all(),
        );
    }

    /** Le CONSTRUCTEUR de parcours est joignable depuis mobile lui aussi. */
    public function test_the_questionnaire_builder_is_reachable_from_mobile(): void
    {
        $module = collect(config('parity.modules'))->firstWhere('key', 'admin-order-engine');

        $this->assertNotNull($module, 'Le constructeur de parcours n’est pas dans le registre.');
        $this->assertSame('/admin/catalogue', $module['path']);
        $this->assertContains('admin', $module['roles']);
    }

    /** Le parcours se rend SANS le chrome du site quand il est embarqué. */
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

    /** Le parcours reste PUBLIC, y compris embarqué. */
    public function test_registering_it_for_mobile_does_not_close_the_public_route(): void
    {
        $this->get('/commander')->assertOk();
        $this->get('/commander/recapitulatif')->assertOk();
    }
}
