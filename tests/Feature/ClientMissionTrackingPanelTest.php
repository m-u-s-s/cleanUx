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

    public function test_client_rendez_vous_page_displays_embedded_mission_tracking_when_mission_exists(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => 'arrived',
        ], withStartCode: true);

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->assertSee('Suivi de mission')
            ->assertSee('Code de début disponible')
            ->assertSee('Actions client');
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
