<?php

namespace Tests\Feature\Phase13;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionTrackingPoint;
use App\Models\MissionTrackingSession;
use App\Models\ProviderPayout;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Eta\EtaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase13Test extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // ProviderPayout model
    // ──────────────────────────────────────────────

    public function test_provider_payout_can_be_marked_paid(): void
    {
        $provider = User::factory()->create();
        $payout = ProviderPayout::create([
            'provider_user_id' => $provider->id,
            'amount'           => 100.50,
            'currency'         => 'EUR',
            'status'           => ProviderPayout::STATUS_PENDING,
            'provider'         => 'stripe_connect',
        ]);

        $payout->markAsPaid('po_test_123');

        $payout->refresh();
        $this->assertSame(ProviderPayout::STATUS_PAID, $payout->status);
        $this->assertSame('po_test_123', $payout->provider_payout_id);
    }

    public function test_provider_payout_scopes_filter_correctly(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        ProviderPayout::create([
            'provider_user_id' => $a->id,
            'amount' => 50, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PAID,
        ]);
        ProviderPayout::create([
            'provider_user_id' => $a->id,
            'amount' => 30, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PENDING,
        ]);
        ProviderPayout::create([
            'provider_user_id' => $b->id,
            'amount' => 99, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PAID,
        ]);

        $this->assertSame(2, ProviderPayout::forProvider($a->id)->count());
        $this->assertSame(2, ProviderPayout::paid()->count());
        $this->assertSame(1, ProviderPayout::pending()->count());
        $this->assertSame(1, ProviderPayout::forProvider($a->id)->paid()->count());
    }

    public function test_payout_helpers(): void
    {
        $payout = ProviderPayout::create([
            'provider_user_id' => User::factory()->create()->id,
            'amount' => 50, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PENDING,
        ]);

        $this->assertTrue($payout->isPending());
        $this->assertFalse($payout->isPaid());

        $payout->markAsProcessing('po_xxx');
        $this->assertSame('processing', $payout->fresh()->status);

        $payout->markAsFailed(['reason' => 'insufficient_funds']);
        $this->assertSame('failed', $payout->fresh()->status);
        $this->assertSame('insufficient_funds', $payout->fresh()->metadata['reason']);
    }

    // ──────────────────────────────────────────────
    // EtaService
    // ──────────────────────────────────────────────

    public function test_haversine_returns_meters_and_seconds(): void
    {
        // Bruxelles → Anvers ~40km
        $result = app(EtaService::class)->computeBetween(50.85, 4.35, 51.22, 4.40);

        $this->assertNotNull($result['meters']);
        $this->assertNotNull($result['seconds']);
        $this->assertSame('haversine', $result['source']); // pas de Google API key dans tests
        $this->assertGreaterThan(35_000, $result['meters']); // au moins 35km
        $this->assertLessThan(50_000, $result['meters']);    // pas plus de 50km
    }

    public function test_compute_for_mission_returns_empty_without_tracking(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status'     => 'planned',
        ]);

        $result = app(EtaService::class)->computeForMission($mission);

        $this->assertNull($result['meters']);
        $this->assertSame('none', $result['source']);
    }

    public function test_compute_for_mission_with_active_tracking(): void
    {
        $booking = $this->makeBooking([
            'destination_lat' => 51.22,
            'destination_lng' => 4.40,
        ]);
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status'     => 'en_route',
        ]);
        $employee = User::factory()->create();

        $session = MissionTrackingSession::create([
            'mission_id'        => $mission->id,
            'employee_user_id'  => $employee->id,
            'tracking_mode'     => 'to_client',
            'is_active'         => true,
            'started_at'        => now(),
            'last_lat'          => 50.85,
            'last_lng'          => 4.35,
        ]);

        $result = app(EtaService::class)->computeForMission($mission);

        $this->assertNotNull($result['meters']);
        $this->assertNotNull($result['seconds']);
        $this->assertContains($result['source'], ['haversine', 'google']);

        // Persisté sur la mission
        $this->assertNotNull($mission->fresh()->last_eta_meters);
    }

    // ──────────────────────────────────────────────
    // ProviderPayouts API
    // ──────────────────────────────────────────────

    public function test_provider_can_list_own_payouts(): void
    {
        $provider = $this->makeProvider();
        $other = $this->makeProvider();

        ProviderPayout::create([
            'provider_user_id' => $provider->id,
            'amount' => 100, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PAID,
        ]);
        ProviderPayout::create([
            'provider_user_id' => $other->id,
            'amount' => 200, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PAID,
        ]);

        $response = $this->actingAs($provider, 'sanctum')->getJson('/api/provider/payouts');

        $response->assertOk();
        $this->assertSame(1, $response->json('pagination.total'));
        $this->assertEquals(100, $response->json('data.0.amount'));
    }

    public function test_provider_summary_calculates_totals(): void
    {
        $provider = $this->makeProvider();

        ProviderPayout::create([
            'provider_user_id' => $provider->id,
            'amount' => 50, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PAID,
            'created_at' => now()->startOfMonth()->addDay(),
        ]);
        ProviderPayout::create([
            'provider_user_id' => $provider->id,
            'amount' => 30, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PENDING,
            'created_at' => now()->startOfMonth()->addDay(),
        ]);

        $response = $this->actingAs($provider, 'sanctum')->getJson('/api/provider/payouts/summary');

        $response->assertOk();
        $this->assertEquals(50.0, $response->json('this_month.paid_amount'));
        $this->assertEquals(30.0, $response->json('this_month.pending_amount'));
    }

    public function test_non_provider_cannot_list_payouts(): void
    {
        $user = User::factory()->create(); // pas de ProviderProfile

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/provider/payouts');

        $response->assertStatus(403);
    }

    public function test_status_filter_works(): void
    {
        $provider = $this->makeProvider();

        ProviderPayout::create([
            'provider_user_id' => $provider->id,
            'amount' => 100, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PAID,
        ]);
        ProviderPayout::create([
            'provider_user_id' => $provider->id,
            'amount' => 50, 'currency' => 'EUR',
            'status' => ProviderPayout::STATUS_PENDING,
        ]);

        $response = $this->actingAs($provider, 'sanctum')
                         ->getJson('/api/provider/payouts?status=paid');

        $response->assertOk();
        $this->assertSame(1, $response->json('pagination.total'));
        $this->assertEquals(100, $response->json('data.0.amount'));
    }

    // ──────────────────────────────────────────────
    // Webhook signature
    // ──────────────────────────────────────────────

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_test_fake']);

        $response = $this->postJson('/webhooks/stripe-connect', [
            'type' => 'account.updated',
            'data' => ['object' => ['id' => 'acct_123']],
        ], [
            'Stripe-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_returns_500_if_secret_missing(): void
    {
        config(['services.stripe.connect_webhook_secret' => null]);
        // Empêche fallback env() depuis le test
        putenv('STRIPE_CONNECT_WEBHOOK_SECRET=');

        $response = $this->postJson('/webhooks/stripe-connect', [
            'type' => 'account.updated',
        ]);

        $response->assertStatus(500);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function makeProvider(): User
    {
        $user = User::factory()->create();
        ProviderProfile::create([
            'user_id'       => $user->id,
            'provider_type' => 'individual',
            'status'        => 'active',
            'verification_status' => 'verified',
        ]);
        return $user->fresh();
    }

    protected function makeBooking(array $overrides = []): Booking
    {
        $client = User::factory()->create();
        return Booking::create(array_merge([
            'booking_reference' => 'CUX-' . strtoupper(Str::random(6)),
            'customer_user_id'  => $client->id,
            'client_id'         => $client->id,
            'scheduled_date'    => now()->addDay()->toDateString(),
            'scheduled_time'    => '10:00:00',
            'status'            => 'confirme',
            'currency'          => 'EUR',
            'priority'          => 'normal',
            'booking_mode'      => 'scheduled',
        ], $overrides));
    }
}
