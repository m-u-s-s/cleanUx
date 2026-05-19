<?php

namespace Tests\Feature\CancellationV2;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancellationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CancellationPoliciesSeeder::class);
        Config::set('cancellation_v2.default_refund_method', 'mock');
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);
    }

    protected function makeBooking(User $client): Booking
    {
        return Booking::create([
            'client_id' => $client->id,
            'date' => now()->addDays(3),
            'heure' => '10:00',
            'scheduled_at' => now()->addDays(3),
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);
    }

    public function test_client_quote_endpoint_returns_calculated_quote(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        Sanctum::actingAs($client);

        $response = $this->getJson("/api/v2/client/bookings/{$booking->id}/cancellation-quote");

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertSame(0, $response->json('quote.fee_amount_cents'));
    }

    public function test_client_cancel_endpoint_executes_and_returns_cancellation(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        Sanctum::actingAs($client);

        $response = $this->postJson("/api/v2/client/bookings/{$booking->id}/cancel", [
            'reason_text' => 'Empêchement personnel',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, BookingCancellationV2::count());
    }

    public function test_client_cancel_endpoint_is_idempotent(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        Sanctum::actingAs($client);

        $this->postJson("/api/v2/client/bookings/{$booking->id}/cancel", [])->assertStatus(201);
        $this->postJson("/api/v2/client/bookings/{$booking->id}/cancel", [])->assertStatus(201);

        $this->assertSame(1, BookingCancellationV2::count());
    }

    public function test_provider_quote_endpoint_uses_provider_policy(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::create([
            'client_id' => $client->id,
            'date' => now()->addHour(),
            'heure' => '10:00',
            'scheduled_at' => now()->addHour(),
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);

        Sanctum::actingAs($provider);
        $response = $this->getJson("/api/v2/provider/bookings/{$booking->id}/cancellation-quote");

        $response->assertOk();
        // Provider <2h: 3000c flat + 25% × 10000 = 5500
        $this->assertSame(5500, $response->json('quote.fee_amount_cents'));
    }

    public function test_admin_override_endpoint_waives_fee(): void
    {
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $booking = Booking::create([
            'client_id' => $client->id,
            'date' => now()->addHour(),
            'heure' => '10:00',
            'scheduled_at' => now()->addHour(),
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);

        $cancellation = app(CancellationEngine::class)->execute($booking->id, $client, 'client');
        $this->assertSame(10000, (int) $cancellation->fee_amount_cents);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/cancellations-v2/{$cancellation->id}/override", [
            'reason' => 'Force majeure exceptionnelle, validée par direction.',
        ]);

        $response->assertOk();
        $this->assertSame(0, (int) $cancellation->fresh()->fee_amount_cents);
    }

    public function test_admin_override_validates_short_reason(): void
    {
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $booking = $this->makeBooking($client);
        $cancellation = app(CancellationEngine::class)->execute($booking->id, $client, 'client');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/cancellations-v2/{$cancellation->id}/override", [
            'reason' => 'no',
        ])->assertStatus(422);
    }

    public function test_admin_index_returns_list(): void
    {
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $booking = $this->makeBooking($client);
        app(CancellationEngine::class)->execute($booking->id, $client, 'client');

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/cancellations-v2');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
