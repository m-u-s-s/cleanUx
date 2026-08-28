<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\FinanceDocumentsClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le budget et la finance etaient deux moities de l'argent du client, jamais reunies :
 * l'une comptait les depenses, l'autre les documents. La finance porte les deux.
 */
class LaFinancePorteLeBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_finance_porte_la_section_du_budget(): void
    {
        Livewire::actingAs(User::factory()->client()->create())
            ->test(FinanceDocumentsClient::class)
            ->assertOk()
            ->assertSee('Mon budget entretien')
            ->assertSee('Par métier')
            ->assertSee('Abonnement ou à la demande');
    }

    /** TEMOIN — les documents restent la : la section s'ajoute, elle ne remplace rien. */
    public function test_temoin_la_finance_montre_toujours_ses_documents(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('client.finance'))
            ->assertOk()
            ->assertSee('Mon budget entretien');
    }

    public function test_la_page_budget_n_a_plus_de_route(): void
    {
        $this->assertFalse(Route::has('client.budget'),
            'La page budget repond encore : la finance devait devenir le seul chemin.');
    }

    /** TEMOIN — la page conservee, elle, repond. */
    public function test_temoin_la_finance_repond(): void
    {
        $this->assertTrue(Route::has('client.finance'));

        $this->actingAs(User::factory()->client()->create())
            ->get(route('client.finance'))
            ->assertOk();
    }
}
