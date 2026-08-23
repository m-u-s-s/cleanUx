<?php

namespace Tests\Feature\Dispatch;

use App\Livewire\Client\PrendreRendezVous;
use App\Livewire\OrderEngine\OrderJourney;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** UN SEUL FORMULAIRE DE RÉSERVATION — la consigne 0, vérifiée là où elle se voit. */
class FormulaireDeReservationUniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    #[Test]
    public function le_tableau_de_bord_client_sert_le_parcours_de_commande(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $this->actingAs($client)
            ->get('/dashboard/client/rendez-vous/nouveau')
            ->assertOk()
            ->assertSeeLivewire(OrderJourney::class);
    }

    #[Test]
    public function l_ancienne_adresse_publique_conduit_au_parcours_de_commande(): void
    {
        $this->get('/prendre-rendez-vous')->assertRedirect('/commander');
    }

    #[Test]
    public function l_ancien_formulaire_n_existe_plus(): void
    {
        // Le composant supprimé ne doit pas revenir par une route oubliée : sa seule existence
        // rouvrirait une deuxième entrée de création de réservation.
        $this->assertFalse(
            class_exists(PrendreRendezVous::class),
            "L'ancien formulaire de réservation doit rester supprimé : deux formulaires = deux entrées de dispatch.",
        );
    }

    #[Test]
    public function le_parcours_connecte_rattache_le_panier_au_client(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $composant = Livewire::actingAs($client)->test(OrderJourney::class);

        // Le panier existe et porte l'identité : l'étape « qui êtes-vous » n'a plus rien à
        // demander à un client déjà connecté.
        $this->assertSame($client->id, $composant->instance()->draft()->client_id);
    }
}
