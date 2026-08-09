<?php

namespace Tests\Feature\Dispatch;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UN SEUL FORMULAIRE DE RÉSERVATION — la consigne 0, vérifiée là où elle se voit.
 *
 * Deux formulaires coexistaient : le parcours public `/commander` (moteur de commande, questions
 * pilotées par l'admin, prix avant identité) et l'ancien formulaire du tableau de bord client.
 * Deux formulaires, c'est deux façons de fixer un prix, deux façons de résoudre une zone, et
 * surtout DEUX ENTRÉES DE DISPATCH — dont une que les règles du moteur de répartition ne
 * traversaient jamais.
 *
 * CE QUI EST VÉRIFIÉ ICI est l'atteignabilité, pas la présence d'un fichier : la route du tableau
 * de bord doit SERVIR le composant du moteur de commande, et l'ancienne adresse publique doit
 * conduire au même endroit. Un test qui lirait la source ne dirait rien de ce qu'un client obtient
 * en ouvrant l'adresse.
 */
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
            class_exists(\App\Livewire\Client\PrendreRendezVous::class),
            "L'ancien formulaire de réservation doit rester supprimé : deux formulaires = deux entrées de dispatch.",
        );
    }

    #[Test]
    public function le_parcours_connecte_rattache_le_panier_au_client(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $composant = \Livewire\Livewire::actingAs($client)->test(OrderJourney::class);

        // Le panier existe et porte l'identité : l'étape « qui êtes-vous » n'a plus rien à
        // demander à un client déjà connecté.
        $this->assertSame($client->id, $composant->instance()->draft()->client_id);
    }
}
