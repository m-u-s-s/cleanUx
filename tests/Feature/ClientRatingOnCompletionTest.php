<?php

namespace Tests\Feature;

use App\Livewire\Client\ClientFeedbackForm;
use App\Livewire\Client\MissionTracking;
use App\Models\Feedback;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

/** L'avis se donne à la clôture, sur la page que le client a déjà sous les yeux. */
class ClientRatingOnCompletionTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

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

    public function test_le_client_peut_noter_le_prestataire_des_la_cloture(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'completed']);

        $this->actingAs($scenario['client']);

        Livewire::test(MissionTracking::class, ['mission' => $scenario['mission']])
            ->assertSee('Merci d’avoir fait confiance à Brio')
            ->assertSeeLivewire(ClientFeedbackForm::class);
    }

    public function test_le_formulaire_est_absent_tant_que_la_mission_court(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'started'], withEndCode: true);

        $this->actingAs($scenario['client']);

        Livewire::test(MissionTracking::class, ['mission' => $scenario['mission']])
            ->assertDontSeeLivewire(ClientFeedbackForm::class);
    }

    /** LE VRAI PARCOURS : le prestataire clôture, PUIS le client note. */
    public function test_l_avis_soumis_est_enregistre_apres_une_vraie_cloture(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'started']);

        // La clôture refuse une mission dont des tâches obligatoires restent ouvertes — garde
        // légitime, satisfaite ici plutôt que contournée.
        $scenario['mission']->load('checklists.items');
        foreach ($scenario['mission']->checklists as $checklist) {
            foreach ($checklist->items as $item) {
                $item->update(['status' => 'done']);
            }
        }

        app(MissionLifecycleService::class)
            ->completeMission($scenario['mission']->fresh(), $scenario['employee']);

        $this->assertSame(
            'termine',
            $scenario['rendezVous']->fresh()->status,
            'La clôture de mission doit marquer la réservation comme terminée.',
        );

        $this->actingAs($scenario['client']);

        Livewire::test(ClientFeedbackForm::class, ['rendezVous' => $scenario['rendezVous']->fresh()])
            ->call('setRating', 4)
            ->set('comment', 'Travail soigné, prestataire ponctuel.')
            ->call('submit')
            ->assertHasNoErrors();

        $avis = Feedback::query()
            ->where('booking_id', $scenario['rendezVous']->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->first();

        $this->assertNotNull($avis, "L'avis du client n'a pas été enregistré.");
        $this->assertSame(4, (int) ($avis->rating ?? $avis->note));
    }
}
