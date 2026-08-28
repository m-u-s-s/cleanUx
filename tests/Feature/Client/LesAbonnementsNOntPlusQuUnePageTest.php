<?php

namespace Tests\Feature\Client;

use App\Livewire\ClientDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `/dashboard/client/abonnements` etait un BOUCHON : treize lignes de composant sans aucune
 * requete, et une vue qui annoncait « Aucun abonnement actif » en dur, meme a qui en avait un.
 */
class LesAbonnementsNOntPlusQuUnePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_bouchon_n_existe_plus(): void
    {
        $this->assertFalse(Route::has('client.subscriptions'),
            'La page qui annoncait « aucun abonnement » en dur repond encore.');
    }

    /** TEMOIN — la vraie page, elle, est bien la. */
    public function test_temoin_la_page_reelle_repond(): void
    {
        $this->assertTrue(Route::has('client.subscriptions-v2'));

        $this->actingAs(User::factory()->client()->create())
            ->get(route('client.subscriptions-v2'))
            ->assertOk();
    }

    /** Le bouton du tableau de bord menait au bouchon : il mene desormais a la vraie page. */
    public function test_le_bouton_du_tableau_de_bord_mene_a_la_vraie_page(): void
    {
        Livewire::actingAs(User::factory()->client()->create())
            ->test(ClientDashboard::class)
            ->assertSee(route('client.subscriptions-v2'), escape: false);
    }
}
