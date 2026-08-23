<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionReinforcementRequest;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionReinforcementService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** LE RENFORT — la troisième issue, celle qui manquait. */
class RenfortDepuisLeTerrainTest extends TestCase
{
    use RefreshDatabase;

    private User $prestataire;

    private function mission(): Mission
    {
        $client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'devis_estime' => 50.00,
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => MissionStatus::ARRIVED,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $this->prestataire->id,
        ]);

        return $mission->fresh('booking');
    }

    public function test_le_prestataire_demande_du_renfort_sans_segment_de_chantier(): void
    {
        $mission = $this->mission();

        $demande = app(MissionReinforcementService::class)
            ->demander($mission, $this->prestataire, 'Deux cents mètres carrés à faire à deux.', 2);

        $this->assertSame('open', $demande->status);
        $this->assertSame(2, $demande->required_people);
        $this->assertNull($demande->mission_task_segment_id, 'aucun segment inventé');
        $this->assertNotNull($demande->needed_at, 'le prestataire est déjà sur place');
    }

    /** UNE SEULE DEMANDE OUVERTE PAR MISSION : deux renforts viendraient pour le même besoin, et le second se déplacerait pour rien, à la charge de la plateforme. */
    public function test_une_seule_demande_ouverte_a_la_fois(): void
    {
        $mission = $this->mission();
        $service = app(MissionReinforcementService::class);

        $service->demander($mission, $this->prestataire, 'Trop gros pour moi seul.');

        $this->expectException(DomainException::class);
        $service->demander($mission, $this->prestataire, 'Encore.');
    }

    public function test_sans_motif_la_demande_est_refusee(): void
    {
        $this->expectExceptionMessage('justifie');
        app(MissionReinforcementService::class)->demander($this->mission(), $this->prestataire, '   ');
    }

    public function test_l_api_ouvre_une_demande(): void
    {
        $mission = $this->mission();
        Sanctum::actingAs($this->prestataire);

        $this->postJson('/api/provider/missions/'.$mission->id.'/reinforcement', [
            'reason' => 'Deux cents mètres carrés à faire à deux.',
            'people' => 2,
        ])->assertCreated()->assertJsonPath('reinforcement.required_people', 2);

        $this->assertSame(1, MissionReinforcementRequest::query()->where('mission_id', $mission->id)->count());
    }

    /** LE TÉMOIN de la garde : un prestataire étranger n'ouvre rien. */
    public function test_un_prestataire_etranger_est_refuse(): void
    {
        $mission = $this->mission();
        $autre = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $autre->id, 'status' => 'active']);
        Sanctum::actingAs($autre);

        $this->postJson('/api/provider/missions/'.$mission->id.'/reinforcement', [
            'reason' => 'Je viens aider.',
        ])->assertForbidden();

        $this->assertSame(0, MissionReinforcementRequest::query()->count());
    }
}
