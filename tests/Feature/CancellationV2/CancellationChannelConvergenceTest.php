<?php

namespace Tests\Feature\CancellationV2;

use App\Models\Booking;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * F1 — the legacy client cancellation API (cancel-with-fee / cancellation-quote) must produce
 * the SAME fee/refund as the CancellationV2 engine the web uses, so cancelling the same booking
 * costs the same regardless of channel.
 */
class CancellationChannelConvergenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CancellationPoliciesSeeder::class);
        Config::set('cancellation_v2.enabled', true);
        Config::set('cancellation_v2.default_refund_method', 'mock');
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);
    }

    private function makeBooking(User $client): Booking
    {
        $scheduledAt = now()->addHours(30); // 24–48h window → 25% client fee in V2 policy
        return Booking::create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'date' => $scheduledAt,
            'heure' => $scheduledAt->format('H:i'),
            'scheduled_at' => $scheduledAt,
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);
    }

    public function test_api_quote_matches_v2_engine_quote(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);

        $engineQuote = app(CancellationEngine::class)->quote($booking->id, 'client');

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/client/bookings/{$booking->id}/cancellation-quote")
            ->assertOk();

        $response->assertJsonPath('quote.fee_amount', $engineQuote->feeAmountCents / 100);
        $response->assertJsonPath('quote.refund_amount', $engineQuote->refundAmountCents / 100);
    }

    public function test_api_cancel_with_fee_applies_v2_fee(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);

        // The V2 quote for this booking (the same one the web path computes).
        $engineQuote = app(CancellationEngine::class)->quote($booking->id, 'client');
        $this->assertSame(2500, $engineQuote->feeAmountCents, 'pre-condition: 25% of €100');

        $response = $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/cancel-with-fee", ['reason' => 'plans changed'])
            ->assertOk();

        $response->assertJsonPath('fee_details.fee_amount', $engineQuote->feeAmountCents / 100);
        $response->assertJsonPath('is_free', false);

        // And the booking is cancelled via the V2 engine (status annule + a V2 row exists).
        $this->assertSame('annule', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_cancellations_v2', ['booking_id' => $booking->id]);
    }
}
