<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionTrackingSession;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientBookingControllerCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    public function test_index_applies_status_date_and_pagination_filters(): void
    {
        $owner = User::factory()->client()->create([
            'organization_account_id' => 4242, // exercises the org branch in the query builder
        ]);

        $this->buildBooking($owner, [
            'status' => 'confirme',
            'scheduled_date' => '2026-06-15',
        ]);
        $this->buildBooking($owner, [
            'status' => 'annule',
            'scheduled_date' => '2026-06-20',
        ]);
        $this->buildBooking($owner, [
            'status' => 'confirme',
            'scheduled_date' => '2026-07-30', // outside the "to" window
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson(
            '/api/client/bookings?status=confirme&from=2026-06-01&to=2026-06-30&per_page=5'
        );

        $response->assertOk();
        $response->assertJsonPath('pagination.per_page', 5);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('confirme', $response->json('data.0.status'));
    }

    public function test_show_returns_detailed_payload_for_owner(): void
    {
        $owner = User::factory()->client()->create();
        $provider = User::factory()->employe()->create(['name' => 'Jean Martin']);
        ProviderProfile::create(['user_id' => $provider->id, 'status' => 'active']);

        $booking = $this->buildBooking($owner, [
            'status' => 'confirme',
            'assigned_provider_user_id' => $provider->id,
            'customer_comment' => 'Apportez le matériel',
            'surface_m2' => 80,
            'destination_lat' => 50.846,
            'destination_lng' => 4.352,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}");

        $response->assertOk();
        $response->assertJsonPath('data.customer_comment', 'Apportez le matériel');
        $response->assertJsonPath('data.surface_m2', 80);
        $this->assertNotNull($response->json('data.destination_lat'));
        $response->assertJsonPath('data.assigned_provider.name', 'Jean Martin');
    }

    public function test_admin_may_view_another_clients_booking(): void
    {
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $booking = $this->buildBooking($client);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id);
    }

    public function test_cancel_rejects_already_cancelled_booking(): void
    {
        $owner = User::factory()->client()->create();
        $booking = $this->buildBooking($owner, ['status' => 'annule']);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/cancel")
            ->assertStatus(422);

        $this->assertSame('annule', $booking->fresh()->status);
    }

    public function test_cancel_rejects_on_site_booking_as_not_cancellable(): void
    {
        $owner = User::factory()->client()->create();
        $booking = $this->buildBooking($owner, ['status' => 'sur_place']);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/cancel")
            ->assertStatus(409);

        $this->assertSame('sur_place', $booking->fresh()->status);
    }

    public function test_eta_reports_no_tracking_when_mission_has_no_active_session(): void
    {
        $owner = User::factory()->client()->create();
        $booking = $this->buildBooking($owner, ['status' => 'confirme']);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'assigned',
            'planned_start_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/eta");

        $response->assertOk();
        $response->assertJsonPath('state', 'no_tracking');
        $response->assertJsonPath('mission_id', $mission->id);
    }

    public function test_eta_computes_distance_with_active_tracking_session(): void
    {
        $owner = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'status' => 'active']);

        $booking = $this->buildBooking($owner, [
            'status' => 'confirme',
            'destination_lat' => 50.846,
            'destination_lng' => 4.352,
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $provider->id,
            'status' => 'en_route',
            'planned_start_at' => now()->addHour(),
        ]);

        MissionTrackingSession::create([
            'mission_id' => $mission->id,
            'employee_user_id' => $provider->id,
            'is_active' => true,
            'is_client_visible' => true,
            'started_at' => now()->subMinutes(5),
            'last_lat' => 50.843,
            'last_lng' => 4.348,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/eta");

        $response->assertOk();
        $response->assertJsonPath('state', 'tracking');
        $response->assertJsonPath('mission_status', 'en_route');
        $response->assertJsonPath('is_client_visible', true);
        $this->assertNotNull($response->json('distance_km'));
        $this->assertNotNull($response->json('eta_minutes'));
    }

    public function test_qr_start_returns_422_when_booking_has_no_mission(): void
    {
        $owner = User::factory()->client()->create();
        $booking = $this->buildBooking($owner, ['status' => 'confirme']);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/qr-start", ['qr_code' => '123456'])
            ->assertStatus(422);
    }

    protected function buildBooking(User $user, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $user->id,
            'client_id' => $user->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'en_attente',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ], $overrides));
    }
}
