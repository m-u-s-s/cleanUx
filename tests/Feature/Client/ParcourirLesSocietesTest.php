<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\BrowseCompanies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `BrowseCompanies` liste les SOCIÉTÉS prestataires vérifiées — ce que
 * `BrowseProviders` ne fait pas : lui liste les prestataires par métier.
 *
 * Le composant, sa vue et ses tests existaient ; il n'avait aucune route et
 * aucun appelant. Sur une place de marché où les deux côtés peuvent être des
 * sociétés, aucun client ne pouvait donc parcourir les sociétés prestataires.
 */
class ParcourirLesSocietesTest extends TestCase
{
    use RefreshDatabase;

    /** TÉMOIN POSITIF — le client atteint la page depuis son espace. */
    public function test_un_client_atteint_la_page_des_societes(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('client.companies.browse'))
            ->assertOk();
    }

    /** REFUS — un visiteur non connecté n'y accède pas. */
    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get(route('client.companies.browse'))->assertRedirect();
    }

    /** Le composant se monte et rend sa vue. */
    public function test_le_composant_se_monte(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(BrowseCompanies::class)
            ->assertOk();
    }

    /** La tuile du répertoire pointe vers une route qui existe vraiment. */
    public function test_la_tuile_existe_et_pointe_vers_la_route(): void
    {
        $tuiles = collect(config('modules.catalogue'))
            ->firstWhere('route', 'client.companies.browse');

        $this->assertNotNull($tuiles, 'La tuile du répertoire est absente de config/modules.php');
        $this->assertSame('client', $tuiles['context']);
        $this->assertTrue(
            Route::has($tuiles['route']),
            'La tuile pointe vers une route inexistante'
        );
    }
}
