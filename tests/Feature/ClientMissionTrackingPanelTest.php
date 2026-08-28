<?php

namespace Tests\Feature;

use App\Livewire\Client\MesRendezVousClient;
use App\Livewire\Client\MissionTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

class ClientMissionTrackingPanelTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    /**
     * Le suivi vivait sur CHAQUE ligne de la liste, qui devenait interminable. Il vit desormais
     * sur la page du rendez-vous. Les trois exigences n'ont pas bouge, seul le lieu a change.
     */
    public function test_la_page_du_rendez_vous_embarque_le_suivi_quand_une_mission_existe(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => 'arrived',
        ], withStartCode: true);

        $this->actingAs($scenario['client'])
            ->get(route('client.rendezvous.show', $scenario['rendezVous']))
            ->assertOk()
            ->assertSee('Suivi de mission')
            ->assertSee('Code de début disponible')
            ->assertSee('Actions client');
    }

    /** TEMOIN — la liste, elle, ne le porte plus : c'est tout l'objet du deplacement. */
    public function test_temoin_la_liste_ne_porte_plus_le_suivi(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'arrived'], withStartCode: true);

        Livewire::actingAs($scenario['client'])->test(MesRendezVousClient::class)
            ->assertDontSee('Suivi de mission');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '50.8466',
                    'lon' => '4.3528',
                    'display_name' => 'Rue de Test 1, 1000 Bruxelles, Belgique',
                ],
            ], 200),
        ]);
    }

    public function test_owner_can_render_mission_tracking_component(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => 'arrived',
        ], withStartCode: true);

        $this->actingAs($scenario['client']);

        Livewire::test(MissionTracking::class, ['mission' => $scenario['mission']])
            ->assertSee('Suivi de mission')
            ->assertSee($scenario['employee']->name)
            ->assertSee('Code de début disponible');
    }
}
