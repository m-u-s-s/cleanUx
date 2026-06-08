<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\MissionEvent;
use App\Models\MissionTrackingSession;
use App\Models\MissionVerificationCode;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MissionFieldActionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_sync_records_one_event_per_action(): void
    {
        [$provider, $mission] = $this->makeMission('assigned');

        $response = $this->actingAs($provider)
            ->postJson('/missions/offline-sync', [
                'actions' => [
                    [
                        'mission_id' => $mission->id,
                        'type' => 'checklist_toggle',
                        'payload' => ['item' => 1],
                        'created_at' => now()->toIso8601String(),
                    ],
                    [
                        'mission_id' => $mission->id,
                        'type' => 'note',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('synced', 2);

        $this->assertSame(2, MissionEvent::where('mission_id', $mission->id)->count());
        $this->assertDatabaseHas('mission_events', [
            'mission_id' => $mission->id,
            'event_type' => 'offline_checklist_toggle',
        ]);
        $this->assertDatabaseHas('mission_events', [
            'mission_id' => $mission->id,
            'event_type' => 'offline_note',
        ]);
    }

    public function test_offline_sync_requires_actions(): void
    {
        [$provider] = $this->makeMission('assigned');

        $this->actingAs($provider)
            ->postJson('/missions/offline-sync', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('actions');
    }

    public function test_toggle_checklist_item_marks_done_and_completes_checklist(): void
    {
        [$provider, $mission] = $this->makeMission('started');

        $checklist = MissionChecklist::factory()->create([
            'mission_id' => $mission->id,
            'status' => 'in_progress',
            'completion_rate' => 0,
        ]);
        $item = MissionChecklistItem::factory()->create([
            'mission_checklist_id' => $checklist->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($provider)
            ->postJson("/mission-checklist-items/{$item->id}/toggle", [
                'status' => 'done',
                'notes' => 'Fait',
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('completion_rate', 100);
        $response->assertJsonPath('checklist_status', 'completed');

        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertSame($provider->id, $item->completed_by_user_id);
        $this->assertNotNull($item->completed_at);
    }

    public function test_toggle_checklist_item_back_to_pending_keeps_in_progress(): void
    {
        [$provider, $mission] = $this->makeMission('started');

        $checklist = MissionChecklist::factory()->create([
            'mission_id' => $mission->id,
            'status' => 'in_progress',
        ]);
        $done = MissionChecklistItem::factory()->create([
            'mission_checklist_id' => $checklist->id,
            'status' => 'done',
        ]);
        $target = MissionChecklistItem::factory()->create([
            'mission_checklist_id' => $checklist->id,
            'status' => 'done',
            'notes' => 'old note',
        ]);

        $response = $this->actingAs($provider)
            ->postJson("/mission-checklist-items/{$target->id}/toggle", [
                'status' => 'pending',
            ]);

        $response->assertOk();
        $response->assertJsonPath('completion_rate', 50);
        $response->assertJsonPath('checklist_status', 'in_progress');

        $target->refresh();
        $this->assertSame('pending', $target->status);
        $this->assertNull($target->completed_by_user_id);
        $this->assertNull($target->completed_at);
        // notes default-preserved branch ($data['notes'] ?? $item->notes)
        $this->assertSame('old note', $target->notes);
    }

    public function test_unassigned_employe_cannot_toggle_checklist_item(): void
    {
        [, $mission] = $this->makeMission('started');

        $checklist = MissionChecklist::factory()->create(['mission_id' => $mission->id]);
        $item = MissionChecklistItem::factory()->create([
            'mission_checklist_id' => $checklist->id,
        ]);

        $stranger = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $stranger->id, 'status' => 'active']);

        $this->actingAs($stranger)
            ->postJson("/mission-checklist-items/{$item->id}/toggle", [
                'status' => 'done',
            ])
            ->assertForbidden();
    }

    public function test_start_validates_code_and_uploads_before_photos(): void
    {
        Storage::fake('private');

        [$provider, $mission] = $this->makeMission('arrived');
        MissionVerificationCode::factory()->startCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('123456'),
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider)
            ->post("/missions/{$mission->id}/start", [
                'code' => '123456',
                'lat' => 50.85,
                'lng' => 4.35,
                'photos_avant' => [UploadedFile::fake()->create('avant.jpg', 100, 'image/jpeg')],
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'started');

        $this->assertSame('started', $mission->fresh()->status);
        $this->assertSame(1, $mission->media()->where('media_type', 'before')->count());
    }

    public function test_start_rejects_invalid_code(): void
    {
        [$provider, $mission] = $this->makeMission('arrived');
        MissionVerificationCode::factory()->startCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('123456'),
            'is_consumed' => false,
        ]);

        $this->actingAs($provider)
            ->post("/missions/{$mission->id}/start", [
                'code' => '000000',
            ])
            ->assertServerError();

        $this->assertSame('arrived', $mission->fresh()->status);
    }

    public function test_en_route_sets_status_and_opens_tracking_session(): void
    {
        [$provider, $mission] = $this->makeMission('assigned');

        $response = $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/en-route", [
                'lat' => 50.85,
                'lng' => 4.35,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'en_route');

        $sessionId = $response->json('tracking_session_id');
        $this->assertNotNull($sessionId);
        $this->assertTrue(
            MissionTrackingSession::where('id', $sessionId)
                ->where('mission_id', $mission->id)
                ->exists()
        );
    }

    public function test_en_route_requires_coordinates(): void
    {
        [$provider, $mission] = $this->makeMission('assigned');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/en-route", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_arrived_sets_status_to_arrived(): void
    {
        [$provider, $mission] = $this->makeMission('en_route');

        $response = $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/arrived", [
                'lat' => 50.85,
                'lng' => 4.35,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'arrived');

        $this->assertSame('arrived', $mission->fresh()->status);
    }

    public function test_finish_validates_end_code_and_uploads_after_photos(): void
    {
        Storage::fake('private');

        [$provider, $mission] = $this->makeMission('started');
        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('654321'),
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider)
            ->post("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => 50.85,
                'lng' => 4.35,
                'photos_apres' => [UploadedFile::fake()->create('apres.jpg', 100, 'image/jpeg')],
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'completed');

        $this->assertSame('completed', $mission->fresh()->status);
        $this->assertSame(1, $mission->media()->where('media_type', 'after')->count());
    }

    /** @return array{0: User, 1: Mission} */
    protected function makeMission(string $status): array
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $provider->id,
            'status' => $status,
            'actual_start_at' => $status === 'started' ? now()->subHour() : null,
            'planned_start_at' => now()->subHour(),
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
        ]);

        return [$provider, $mission];
    }
}
