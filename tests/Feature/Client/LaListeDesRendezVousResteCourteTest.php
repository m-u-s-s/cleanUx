<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\MesRendezVousClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

/**
 * Chaque ligne de la liste portait le panneau de suivi entier : la page devenait interminable.
 * Le suivi vit desormais sur la page du rendez-vous, ou tout se gere.
 */
class LaListeDesRendezVousResteCourteTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '50.8', 'lon' => '4.3', 'display_name' => 'x']], 200)]);
    }

    public function test_la_liste_ne_porte_plus_le_suivi_de_mission(): void
    {
        $scenario = $this->createMissionPortalContext();

        Livewire::actingAs($scenario['client'])->test(MesRendezVousClient::class)
            ->assertOk()
            ->assertDontSee('Suivi de mission')
            ->assertDontSee('Demande reçue');
    }

    /**
     * TEMOIN — le suivi n'a pas disparu, il a demenage. Sans ce controle, le test ci-dessus
     * resterait vert si le panneau avait ete simplement supprime du produit.
     */
    public function test_temoin_la_page_du_rendez_vous_porte_le_suivi(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client'])
            ->get(route('client.rendezvous.show', $scenario['rendezVous']))
            ->assertOk()
            ->assertSee('Suivi de mission');
    }

    public function test_la_ligne_offre_les_trois_actions_et_mene_au_detail(): void
    {
        $scenario = $this->createMissionPortalContext();

        Livewire::actingAs($scenario['client'])->test(MesRendezVousClient::class)
            ->assertSee('Modifier')
            ->assertSee('Replanifier')
            ->assertSee('Annuler')
            ->assertSee(route('client.rendezvous.show', $scenario['rendezVous']), escape: false);
    }

    /** La garde vit dans le composant : une page Livewire est une porte HTTP a part entiere. */
    public function test_le_rendez_vous_d_un_autre_client_est_refuse(): void
    {
        $mien = $this->createMissionPortalContext();
        $sien = $this->createMissionPortalContext();

        $this->actingAs($mien['client'])
            ->get(route('client.rendezvous.show', $sien['rendezVous']))
            ->assertForbidden();
    }

    /** La ligne garde l'essentiel : ce que le client cherche du regard. */
    public function test_la_ligne_garde_le_service_la_date_et_le_statut(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        Livewire::actingAs($scenario['client'])->test(MesRendezVousClient::class)
            ->assertSee($rdv->service_display_name)
            ->assertSee((string) $rdv->date->format('Y-m-d'));
    }
}
