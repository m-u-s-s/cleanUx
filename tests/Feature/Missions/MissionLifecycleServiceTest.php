<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\EmployeArriveNotification;
use App\Notifications\EmployeEnRouteNotification;
use App\Notifications\MissionCompletedNotification;
use App\Notifications\MissionStartedNotification;
use App\Services\Missions\MissionLifecycleService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MissionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MissionLifecycleService
    {
        return app(MissionLifecycleService::class);
    }

    /**
     * @return array{0: User, 1: User, 2: Mission, 3: Booking}
     */
    private function makeMission(string $status): array
    {
        $client = User::factory()->client()->create(['phone' => '+32470000000']);
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
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'devis_estime' => 120,
        ]);

        $mission = Mission::create([
            'rendez_vous_id' => $booking->id,
            'lead_provider_user_id' => $provider->id,
            'status' => $status,
            'planned_start_at' => now()->subHour(),
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
        ]);

        return [$client, $provider, $mission, $booking];
    }

    public function test_set_en_route_transitions_and_notifies_client(): void
    {
        Notification::fake();
        [$client, $provider, $mission] = $this->makeMission(MissionStatus::ASSIGNED);

        $result = $this->service()->setEnRoute($mission, $provider);

        $this->assertSame(MissionStatus::EN_ROUTE, $result->fresh()->status);
        $this->assertSame('accepted', $mission->assignments()->where('user_id', $provider->id)->value('assignment_status'));
        Notification::assertSentTo($client, EmployeEnRouteNotification::class);
    }

    public function test_set_arrived_generates_codes_stores_session_and_notifies(): void
    {
        Notification::fake();
        [$client, $provider, $mission] = $this->makeMission(MissionStatus::EN_ROUTE);

        $result = $this->service()->setArrived($mission, $provider, 50.85, 4.35);

        $fresh = $result->fresh();
        $this->assertSame(MissionStatus::ARRIVED, $fresh->status);
        $this->assertEquals(50.85, (float) $fresh->start_lat);
        $this->assertEquals(4.35, (float) $fresh->start_lng);

        // Both a start and an end verification code are created.
        $this->assertSame(1, $mission->verificationCodes()->where('code_type', 'start')->count());
        $this->assertSame(1, $mission->verificationCodes()->where('code_type', 'end')->count());
        $this->assertNotNull(session('mission_start_code_'.$mission->id));

        Notification::assertSentTo($client, EmployeArriveNotification::class);
    }

    public function test_generate_start_code_returns_plain_code_and_record(): void
    {
        [, , $mission] = $this->makeMission(MissionStatus::ARRIVED);

        $generated = $this->service()->generateStartCode($mission);

        $this->assertArrayHasKey('code', $generated);
        $this->assertArrayHasKey('record', $generated);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $generated['code']);
    }

    public function test_validate_start_code_starts_mission(): void
    {
        Notification::fake();
        [$client, $provider, $mission] = $this->makeMission(MissionStatus::ARRIVED);

        $generated = $this->service()->generateStartCode($mission);

        $result = $this->service()->validateStartCode($mission, $provider, $generated['code'], 50.0, 4.0);

        $fresh = $result->fresh();
        $this->assertSame(MissionStatus::STARTED, $fresh->status);
        $this->assertNotNull($fresh->actual_start_at);
        $this->assertSame($provider->id, $fresh->started_by_user_id);
        $this->assertTrue((bool) $fresh->client_presence_confirmed);
        Notification::assertSentTo($client, MissionStartedNotification::class);
    }

    public function test_validate_start_code_rejects_wrong_code(): void
    {
        [, $provider, $mission] = $this->makeMission(MissionStatus::ARRIVED);

        $this->service()->generateStartCode($mission);

        $this->expectException(RuntimeException::class);
        $this->service()->validateStartCode($mission, $provider, '000000');
    }

    public function test_validate_start_code_from_qr_starts_mission(): void
    {
        Notification::fake();
        [$client, $provider, $mission] = $this->makeMission(MissionStatus::ARRIVED);

        $result = $this->service()->validateStartCodeFromQr($mission, $provider, 50.1, 4.1);

        $fresh = $result->fresh();
        $this->assertSame(MissionStatus::STARTED, $fresh->status);
        $this->assertSame($provider->id, $fresh->started_by_user_id);
        $this->assertTrue((bool) $fresh->client_presence_confirmed);
        Notification::assertSentTo($client, MissionStartedNotification::class);
    }

    public function test_generate_end_code_requires_started_status(): void
    {
        [, , $mission] = $this->makeMission(MissionStatus::ASSIGNED);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('démarrée');
        $this->service()->generateEndCode($mission);
    }

    public function test_generate_end_code_succeeds_when_started(): void
    {
        [, , $mission] = $this->makeMission(MissionStatus::STARTED);

        $generated = $this->service()->generateEndCode($mission);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $generated['code']);
        $this->assertSame(1, $mission->verificationCodes()->where('code_type', 'end')->count());
    }

    public function test_validate_end_code_completes_mission(): void
    {
        Notification::fake();
        [$client, $provider, $mission] = $this->makeMission(MissionStatus::STARTED);
        $mission->update(['actual_start_at' => now()->subHour()]);

        $generated = $this->service()->generateEndCode($mission);

        $result = $this->service()->validateEndCode($mission, $provider, $generated['code'], 50.2, 4.2);

        $fresh = $result->fresh();
        $this->assertSame(MissionStatus::COMPLETED, $fresh->status);
        $this->assertNotNull($fresh->actual_end_at);
        $this->assertSame($provider->id, $fresh->closed_by_user_id);
        Notification::assertSentTo($client, MissionCompletedNotification::class);
    }

    public function test_complete_mission_blocks_when_required_checklist_item_pending(): void
    {
        [, $provider, $mission] = $this->makeMission(MissionStatus::STARTED);

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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('obligatoires');
        $this->service()->completeMission($mission, $provider);
    }

    public function test_set_en_route_rejects_unassigned_user(): void
    {
        [, , $mission] = $this->makeMission(MissionStatus::ASSIGNED);
        $stranger = User::factory()->employe()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non affecté');
        $this->service()->setEnRoute($mission, $stranger);
    }
}
