<?php

namespace Tests\Feature\Phase11;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderMissionAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_returns_pending_offers_for_authenticated_provider(): void
    {
        $provider = $this->makeProvider();
        $mission = $this->makeMission();

        $assignment = $this->makeAssignment($mission, $provider, [
            'assignment_status' => 'assigned',
            'expires_at' => now()->addMinutes(5),
            'notification_sent_at' => now(),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox');

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'count' => 1,
        ]);
        $response->assertJsonPath('data.0.id', $assignment->id);
        $response->assertJsonPath('data.0.assignment_status', 'assigned');
        $response->assertJsonPath('data.0.mission_id', $mission->id);
    }

    public function test_inbox_excludes_expired_and_non_assigned_offers(): void
    {
        $provider = $this->makeProvider();

        // Expired offer (still "assigned" but past expires_at) → excluded.
        $this->makeAssignment($this->makeMission(), $provider, [
            'assignment_status' => 'assigned',
            'expires_at' => now()->subMinutes(5),
        ]);

        // Declined offer (different mission to satisfy the unique key) → excluded.
        $this->makeAssignment($this->makeMission(), $provider, [
            'assignment_status' => 'declined',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox');

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'count' => 0,
            'data' => [],
        ]);
    }

    public function test_show_returns_detailed_offer(): void
    {
        $provider = $this->makeProvider();
        $mission = $this->makeMission();
        $assignment = $this->makeAssignment($mission, $provider, [
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/assignments/{$assignment->id}");

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.id', $assignment->id);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'mission_id',
                'assignment_status',
                'remaining_seconds',
                'mission' => ['id', 'planned_start_at'],
            ],
        ]);
        $response->assertJsonPath('data.mission.id', $mission->id);
        // remaining_seconds is computed from a future expires_at.
        $this->assertGreaterThan(0, $response->json('data.remaining_seconds'));
    }

    public function test_show_rejects_offer_owned_by_another_provider(): void
    {
        $owner = $this->makeProvider();
        $intruder = $this->makeProvider();
        $mission = $this->makeMission();
        $assignment = $this->makeAssignment($mission, $owner);

        $response = $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/provider/assignments/{$assignment->id}");

        $response->assertStatus(403);
    }

    public function test_accept_marks_offer_accepted(): void
    {
        Bus::fake();

        $provider = $this->makeProvider();
        $mission = $this->makeMission();
        $assignment = $this->makeAssignment($mission, $provider, [
            'assignment_status' => 'assigned',
            'expires_at' => now()->addMinutes(5),
            'notification_sent_at' => now()->subSeconds(3),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/assignments/{$assignment->id}/accept");

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('assignment_id', $assignment->id);
        $response->assertJsonPath('status', 'accepted');

        $this->assertSame('accepted', $assignment->fresh()->assignment_status);
        $this->assertSame('assigned', $mission->fresh()->status);
        $this->assertSame($provider->id, $mission->fresh()->lead_provider_user_id);
    }

    public function test_accept_returns_409_when_offer_not_acceptable(): void
    {
        Bus::fake();

        $provider = $this->makeProvider();
        $mission = $this->makeMission();
        $assignment = $this->makeAssignment($mission, $provider, [
            'assignment_status' => 'declined',
            'declined_at' => now(),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/assignments/{$assignment->id}/accept");

        $response->assertStatus(409);
        $response->assertJsonPath('ok', false);
        $this->assertArrayHasKey('error', $response->json());
    }

    public function test_decline_marks_offer_declined_with_reason(): void
    {
        Bus::fake();

        $provider = $this->makeProvider();
        $mission = $this->makeMission();
        $assignment = $this->makeAssignment($mission, $provider, [
            'assignment_status' => 'assigned',
            'expires_at' => now()->addMinutes(5),
            'notification_sent_at' => now()->subSeconds(2),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/assignments/{$assignment->id}/decline", [
                'reason' => 'Trop loin',
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'declined');
        // No eligible candidates → no re-assignment.
        $response->assertJsonPath('reassigned_to_other', false);

        $fresh = $assignment->fresh();
        $this->assertSame('declined', $fresh->assignment_status);
        $this->assertSame('Trop loin', $fresh->decline_reason);
    }

    public function test_decline_validates_reason_length(): void
    {
        Bus::fake();

        $provider = $this->makeProvider();
        $mission = $this->makeMission();
        $assignment = $this->makeAssignment($mission, $provider, [
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/assignments/{$assignment->id}/decline", [
                'reason' => str_repeat('x', 300),
            ]);

        $response->assertStatus(422);
        $this->assertSame('assigned', $assignment->fresh()->assignment_status);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    protected function makeProvider(): User
    {
        $user = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user->fresh();
    }

    protected function makeBooking(): Booking
    {
        $client = User::factory()->create();

        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);
    }

    protected function makeMission(?Booking $booking = null): Mission
    {
        $booking ??= $this->makeBooking();

        return Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
        ]);
    }

    protected function makeAssignment(Mission $mission, User $provider, array $overrides = []): MissionAssignment
    {
        return MissionAssignment::factory()->create(array_merge([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
        ], $overrides));
    }
}
