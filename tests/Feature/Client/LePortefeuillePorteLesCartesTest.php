<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\WalletClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Les cartes bancaires et les credits etaient deux moities du meme espace « argent »,
 * jamais reunies. Le portefeuille porte les deux ; la page des cartes n'a plus de route.
 */
class LePortefeuillePorteLesCartesTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_portefeuille_porte_la_section_des_moyens_de_paiement(): void
    {
        Livewire::actingAs(User::factory()->client()->create())
            ->test(WalletClient::class)
            ->assertOk()
            ->assertSee('Mes moyens de paiement')
            ->assertSee('Ajouter une carte');
    }

    /** TEMOIN — les credits restent la : la section ne remplace rien, elle s'ajoute. */
    public function test_temoin_le_portefeuille_montre_toujours_ses_credits(): void
    {
        Livewire::actingAs(User::factory()->client()->create())
            ->test(WalletClient::class)
            ->assertSee('Historique des crédits');
    }

    public function test_la_page_des_cartes_n_a_plus_de_route(): void
    {
        $this->assertFalse(Route::has('client.payment.methods'),
            'La page des cartes repond encore : le portefeuille devait devenir le seul chemin.');
    }

    /** Le portefeuille reste joignable et rend les deux sujets. */
    public function test_le_portefeuille_repond(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('client.wallet'))
            ->assertOk()
            ->assertSee('Mes moyens de paiement');
    }
}
