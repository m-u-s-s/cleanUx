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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderMissionLifecycleControllerCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Mission, 3: Booking}
     */
    private function buildMission(string $status, bool $withAssignment = true): array
    {
        $client = User::factory()->client()->create([
            'name' => 'Alice Dupont',
            'phone' => '+32471000001',
        ]);
        $provider = User::factory()->employe()->create();
        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'telephone_client' => '+32470111222',
            'address' => 'Rue de la Loi 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'customer_comment' => 'Apporter matériel',
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
            'status' => $status,
            'planned_start_at' => now()->subHour(),
        ]);

        if ($withAssignment) {
            MissionAssignment::factory()->accepted()->create([
                'mission_id' => $mission->id,
                'user_id' => $provider->id,
            ]);
        }

        return [$client, $provider, $mission, $booking];
    }

    public function test_active_lists_only_provider_missions_with_active_status(): void
    {
        [, $provider, $mission] = $this->buildMission(MissionStatus::ASSIGNED);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/missions/active');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('count', 1);
        $response->assertJsonPath('data.0.id', $mission->id);
        $response->assertJsonPath('data.0.status', MissionStatus::ASSIGNED);
    }

    public function test_show_returns_detailed_payload_for_authorized_provider(): void
    {
        [, $provider, $mission] = $this->buildMission(MissionStatus::ASSIGNED);

        $checklist = MissionChecklist::create([
            'mission_id' => $mission->id,
            'template_name' => 'Standard',
            'status' => 'in_progress',
        ]);
        MissionChecklistItem::create([
            'mission_checklist_id' => $checklist->id,
            'label' => 'Vider les poubelles',
            'is_required' => true,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}");

        $response->assertOk();
        // Payload plat depuis la réparation du contrat de détail : les clés imbriquées
        // `client.name` / `booking.customer_comment` n'existent plus (le type TS Mission côté
        // mobile est plat, voir ProviderMissionDetailContractTest).
        $response->assertJsonPath('data.id', $mission->id);
        $response->assertJsonPath('data.client_name', 'Alice Dupont');
        $response->assertJsonPath('data.notes', 'Apporter matériel');
        $response->assertJsonPath('data.checklists_count', 1);
        $response->assertJsonPath('data.checklist_items_pending', 1);
    }

    public function test_show_forbidden_for_unassigned_provider(): void
    {
        [, , $mission] = $this->buildMission(MissionStatus::ASSIGNED);
        $stranger = User::factory()->employe()->create();

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}");

        $response->assertStatus(403);
    }

    public function test_start_transitions_mission_to_en_route(): void
    {
        Notification::fake();
        [, $provider, $mission] = $this->buildMission(MissionStatus::ASSIGNED);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/start", [
                'lat' => 50.843,
                'lng' => 4.348,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', MissionStatus::EN_ROUTE);
        $this->assertSame(MissionStatus::EN_ROUTE, $mission->fresh()->status);
    }

    public function test_start_rejects_out_of_range_latitude(): void
    {
        [, $provider, $mission] = $this->buildMission(MissionStatus::ASSIGNED);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/start", [
                'lat' => 999,
            ]);

        $response->assertStatus(422);
    }

    public function test_arrive_generates_verification_codes(): void
    {
        Notification::fake();
        [, $provider, $mission] = $this->buildMission(MissionStatus::EN_ROUTE);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/arrive", [
                'lat' => 50.846,
                'lng' => 4.352,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', MissionStatus::ARRIVED);
        $this->assertSame(
            1,
            $mission->verificationCodes()->where('code_type', 'end')->where('is_consumed', false)->count()
        );
    }

    public function test_complete_requires_end_code_when_pending_code_exists(): void
    {
        Notification::fake();
        [, $provider, $mission] = $this->buildMission(MissionStatus::EN_ROUTE);

        // Arrive first to generate a pending end verification code.
        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/arrive")
            ->assertOk();

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete");

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('message', 'Le code de fin est requis pour clôturer cette mission.');
    }

    public function test_complete_closes_mission_without_pending_end_code(): void
    {
        Notification::fake();
        [, $provider, $mission] = $this->buildMission(MissionStatus::STARTED);
        $mission->update(['actual_start_at' => now()->subHour()]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete", [
                'lat' => 50.846,
                'lng' => 4.352,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', MissionStatus::COMPLETED);
        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);
    }
}
