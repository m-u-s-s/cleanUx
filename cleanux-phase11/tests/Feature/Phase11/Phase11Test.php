<?php

namespace Tests\Feature\Phase11;

use App\Jobs\Dispatch\EscalateMissionAssignmentJob;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Dispatch\AiDispatchService;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\Provider\ProviderPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase11Test extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(array $overrides = []): User
    {
        $user = User::factory()->create();
        ProviderProfile::create(array_merge([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ], $overrides));
        return $user->fresh();
    }

    // ──────────────────────────────────────────────────────
    // ProviderPresenceService
    // ──────────────────────────────────────────────────────

    public function test_provider_can_go_online(): void
    {
        $user = $this->makeProvider();

        $profile = app(ProviderPresenceService::class)->goOnline(
            $user, 50.85, 4.35, ['battery_level' => 80]
        );

        $this->assertTrue((bool) $profile->is_online);
        $this->assertNotNull($profile->went_online_at);
        $this->assertSame('50.8500000', (string) $profile->current_lat);
        $this->assertSame('4.3500000', (string) $profile->current_lng);
        $this->assertNotNull($profile->last_heartbeat_at);
    }

    public function test_provider_can_go_offline(): void
    {
        $user = $this->makeProvider();
        $service = app(ProviderPresenceService::class);

        $service->goOnline($user, 50.85, 4.35);
        $profile = $service->goOffline($user);

        $this->assertFalse((bool) $profile->is_online);
        $this->assertNotNull($profile->went_offline_at);
    }

    public function test_heartbeat_updates_position_when_online(): void
    {
        $user = $this->makeProvider();
        $service = app(ProviderPresenceService::class);

        $service->goOnline($user, 50.85, 4.35);
        $profile = $service->heartbeat($user, 50.86, 4.36);

        $this->assertNotNull($profile);
        $this->assertSame('50.8600000', (string) $profile->current_lat);
        $this->assertSame('4.3600000', (string) $profile->current_lng);
    }

    public function test_heartbeat_returns_null_when_offline(): void
    {
        $user = $this->makeProvider();
        $service = app(ProviderPresenceService::class);

        $result = $service->heartbeat($user, 50.85, 4.35);

        $this->assertNull($result);
    }

    public function test_clean_stale_presence_disables_old_online_providers(): void
    {
        $user1 = $this->makeProvider();
        $user2 = $this->makeProvider();
        $service = app(ProviderPresenceService::class);

        // user1 va online avec heartbeat ancien
        $service->goOnline($user1, 50.85, 4.35);
        $user1->providerProfile->update(['last_heartbeat_at' => now()->subMinutes(10)]);

        // user2 va online avec heartbeat récent
        $service->goOnline($user2, 50.85, 4.35);

        $count = $service->cleanStalePresence();

        $this->assertSame(1, $count);
        $this->assertFalse((bool) $user1->providerProfile->fresh()->is_online);
        $this->assertTrue((bool) $user2->providerProfile->fresh()->is_online);
    }

    public function test_find_online_near_returns_providers_in_radius(): void
    {
        $user1 = $this->makeProvider();
        $user2 = $this->makeProvider();
        $service = app(ProviderPresenceService::class);

        // user1 à Bruxelles
        $service->goOnline($user1, 50.85, 4.35);
        // user2 à Liège (~90km de Bruxelles)
        $service->goOnline($user2, 50.63, 5.57);

        $nearBrussels = $service->findOnlineNear(50.85, 4.35, 50);

        $this->assertSame(1, $nearBrussels->count());
        $this->assertSame($user1->id, $nearBrussels->first()->user_id);
    }

    public function test_go_online_throws_if_not_provider(): void
    {
        $user = User::factory()->create();

        $this->expectException(\DomainException::class);
        app(ProviderPresenceService::class)->goOnline($user, 50.85, 4.35);
    }

    // ──────────────────────────────────────────────────────
    // MissionDispatchService
    // ──────────────────────────────────────────────────────

    public function test_create_offer_assigns_provider_with_expiry(): void
    {
        Bus::fake();

        $user = $this->makeProvider();
        $booking = $this->makeBooking();
        $mission = $this->makeMission($booking);

        $assignment = app(MissionDispatchService::class)->createOffer($mission, $user);

        $this->assertSame('assigned', $assignment->assignment_status);
        $this->assertSame($user->id, $assignment->user_id);
        $this->assertNotNull($assignment->notification_sent_at);
        $this->assertNotNull($assignment->expires_at);
        $this->assertTrue($assignment->expires_at->greaterThan(now()));

        Bus::assertDispatched(EscalateMissionAssignmentJob::class);
    }

    public function test_accept_marks_assignment_and_mission(): void
    {
        Bus::fake();

        $user = $this->makeProvider();
        $booking = $this->makeBooking();
        $mission = $this->makeMission($booking);

        $assignment = app(MissionDispatchService::class)->createOffer($mission, $user);
        $accepted = app(MissionDispatchService::class)->accept($assignment);

        $this->assertSame('accepted', $accepted->assignment_status);
        $this->assertNotNull($accepted->accepted_at);
        $this->assertNotNull($accepted->response_seconds);
        $this->assertSame('assigned', $mission->fresh()->status);
        $this->assertSame($user->id, $mission->fresh()->lead_provider_user_id);
    }

    public function test_accept_cancels_other_pending_offers_for_same_mission(): void
    {
        Bus::fake();

        $user1 = $this->makeProvider();
        $user2 = $this->makeProvider();
        $booking = $this->makeBooking();
        $mission = $this->makeMission($booking);
        $service = app(MissionDispatchService::class);

        $a1 = $service->createOffer($mission, $user1);
        $a2 = $service->createOffer($mission, $user2);

        $service->accept($a1);

        $this->assertSame('cancelled', $a2->fresh()->assignment_status);
    }

    public function test_decline_does_not_accept(): void
    {
        Bus::fake();

        $user = $this->makeProvider();
        $booking = $this->makeBooking();
        $mission = $this->makeMission($booking);
        $service = app(MissionDispatchService::class);

        $assignment = $service->createOffer($mission, $user);
        // On override AiDispatchService pour ne pas escalader (pas de candidat)
        $service->decline($assignment, 'too far');

        $this->assertSame('declined', $assignment->fresh()->assignment_status);
        $this->assertSame('too far', $assignment->fresh()->decline_reason);
    }

    public function test_cannot_accept_expired_offer(): void
    {
        Bus::fake();

        $user = $this->makeProvider();
        $booking = $this->makeBooking();
        $mission = $this->makeMission($booking);
        $service = app(MissionDispatchService::class);

        $assignment = $service->createOffer($mission, $user);

        // Force expiration
        $assignment->update(['expires_at' => now()->subSeconds(5)]);

        $this->expectException(\DomainException::class);
        $service->accept($assignment->fresh());
    }

    public function test_cannot_accept_already_accepted_offer(): void
    {
        Bus::fake();

        $user = $this->makeProvider();
        $booking = $this->makeBooking();
        $mission = $this->makeMission($booking);
        $service = app(MissionDispatchService::class);

        $assignment = $service->createOffer($mission, $user);
        $service->accept($assignment);

        $this->expectException(\DomainException::class);
        $service->accept($assignment->fresh());
    }

    public function test_expire_and_escalate_on_already_accepted_does_nothing(): void
    {
        Bus::fake();

        $user = $this->makeProvider();
        $booking = $this->makeBooking();
        $mission = $this->makeMission($booking);
        $service = app(MissionDispatchService::class);

        $assignment = $service->createOffer($mission, $user);
        $service->accept($assignment);

        // Maintenant le job est exécuté APRÈS l'accept
        $result = $service->expireAndEscalate($assignment->fresh());

        $this->assertNull($result);
        $this->assertSame('accepted', $assignment->fresh()->assignment_status);
    }

    // ──────────────────────────────────────────────────────
    // API endpoints
    // ──────────────────────────────────────────────────────

    public function test_api_provider_can_go_online_via_endpoint(): void
    {
        $user = $this->makeProvider();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/provider/presence/online', [
            'lat' => 50.85,
            'lng' => 4.35,
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'is_online' => true]);

        $this->assertTrue((bool) $user->providerProfile->fresh()->is_online);
    }

    public function test_api_non_provider_cannot_go_online(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/provider/presence/online', [
            'lat' => 50.85,
            'lng' => 4.35,
        ]);

        $response->assertStatus(403);
    }

    public function test_api_validation_rejects_invalid_lat(): void
    {
        $user = $this->makeProvider();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/provider/presence/online', [
            'lat' => 999,
            'lng' => 4.35,
        ]);

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    protected function makeBooking(): Booking
    {
        $client = User::factory()->create();
        return Booking::create([
            'booking_reference' => 'CUX-' . strtoupper(Str::random(6)),
            'customer_user_id'  => $client->id,
            'client_id'         => $client->id,
            'scheduled_date'    => now()->addDay()->toDateString(),
            'scheduled_time'    => '10:00:00',
            'status'            => 'confirme',
            'currency'          => 'EUR',
            'priority'          => 'normal',
            'booking_mode'      => 'scheduled',
        ]);
    }

    protected function makeMission(Booking $booking): Mission
    {
        return Mission::create([
            'booking_id' => $booking->id,
            'status'     => 'planned',
            'planned_start_at' => now()->addDay(),
        ]);
    }
}
