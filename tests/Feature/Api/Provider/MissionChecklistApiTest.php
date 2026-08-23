<?php

namespace Tests\Feature\Api\Provider;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** La checklist qui conditionne la clôture, exposée au mobile. */
class MissionChecklistApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Mission, 2: MissionChecklist}
     */
    private function construire(int $obligatoires = 2): array
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'devis_estime' => 120,
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $provider->id,
            'status' => MissionStatus::STARTED,
            'planned_start_at' => now()->subHour(),
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
        ]);

        $checklist = MissionChecklist::create([
            'mission_id' => $mission->id,
            'template_name' => 'Checklist standard',
            'status' => 'draft',
        ]);

        for ($i = 1; $i <= $obligatoires; $i++) {
            MissionChecklistItem::create([
                'mission_checklist_id' => $checklist->id,
                'label' => 'Tâche obligatoire '.$i,
                'is_required' => true,
                'status' => 'pending',
            ]);
        }

        MissionChecklistItem::create([
            'mission_checklist_id' => $checklist->id,
            'label' => 'Tâche facultative',
            'is_required' => false,
            'status' => 'pending',
        ]);

        return [$provider, $mission, $checklist];
    }

    public function test_la_liste_expose_les_taches_et_ce_qui_bloque(): void
    {
        [$provider, $mission] = $this->construire();

        $reponse = $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/checklist");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.required_pending', 2);
        $reponse->assertJsonPath('data.blocks_completion', true);
        $reponse->assertJsonPath('data.checklists.0.name', 'Checklist standard');
        $reponse->assertJsonCount(3, 'data.checklists.0.items');
        $reponse->assertJsonPath('data.checklists.0.items.0.is_required', true);
        $reponse->assertJsonPath('data.checklists.0.items.0.done', false);
    }

    public function test_cocher_une_tache_fait_baisser_ce_qui_bloque(): void
    {
        [$provider, $mission, $checklist] = $this->construire();
        $tache = $checklist->items()->where('is_required', true)->first();

        $reponse = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/checklist/{$tache->id}", [
                'status' => 'done',
            ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('data.required_pending', 1);
        $reponse->assertJsonPath('data.blocks_completion', true);

        $this->assertSame('done', $tache->fresh()->status);
        $this->assertSame($provider->id, (int) $tache->fresh()->completed_by_user_id);
    }

    /** Une tâche facultative ouverte ne doit PAS empêcher de clôturer. */
    public function test_seules_les_taches_obligatoires_bloquent(): void
    {
        [$provider, $mission, $checklist] = $this->construire();

        foreach ($checklist->items()->where('is_required', true)->get() as $tache) {
            $this->actingAs($provider, 'sanctum')
                ->postJson("/api/provider/missions/{$mission->id}/checklist/{$tache->id}", ['status' => 'done'])
                ->assertOk();
        }

        $reponse = $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/checklist");

        $reponse->assertJsonPath('data.required_pending', 0);
        $reponse->assertJsonPath('data.blocks_completion', false);
    }

    /** Décocher reste possible : une erreur juste avant la clôture doit pouvoir être reprise. */
    public function test_une_tache_peut_etre_decochee(): void
    {
        [$provider, $mission, $checklist] = $this->construire();
        $tache = $checklist->items()->where('is_required', true)->first();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/checklist/{$tache->id}", ['status' => 'done']);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/checklist/{$tache->id}", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.required_pending', 2);

        $this->assertNull($tache->fresh()->completed_at);
    }

    /** L'ASSIGNATION AUTORISE SA MISSION, PAS TOUTES. */
    public function test_une_tache_d_une_autre_mission_est_refusee(): void
    {
        [$provider, $mission] = $this->construire();
        [, , $autreChecklist] = $this->construire();
        $tacheEtrangere = $autreChecklist->items()->first();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/checklist/{$tacheEtrangere->id}", [
                'status' => 'done',
            ])
            ->assertNotFound();

        $this->assertSame('pending', $tacheEtrangere->fresh()->status);
    }

    public function test_un_prestataire_non_assigne_est_refuse(): void
    {
        [, $mission] = $this->construire();
        $etranger = User::factory()->employe()->create();

        $this->actingAs($etranger, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}/checklist")
            ->assertForbidden();
    }
}
