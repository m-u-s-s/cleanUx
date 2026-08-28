<?php

namespace Tests\Feature\Cancellation;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Cancellation\CancelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Coverage for App\Services\Cancellation\CancelBookingService. */
class CancelBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────
    // cancelByClient
    // ──────────────────────────────────────────────

    // ──────────────────────────────────────────────
    // cancelByProvider
    // ──────────────────────────────────────────────

    public function test_cancel_by_provider_in_free_window_does_not_penalize(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $provider = User::factory()->create();
        $profile = ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'metadata' => [],
        ]);

        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '12:00:00', // 2h before → free window (>30min)
        ]);

        $result = app(CancelBookingService::class)->cancelByProvider($booking, $provider, 'imprévu');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['penalty']['is_free']);
        $this->assertSame(0.0, $result['penalty']['penalty_eur']);

        $fresh = $booking->fresh();
        $this->assertSame('en_attente', $fresh->status);
        $this->assertStringContainsString('imprévu', $fresh->cancellation_reason);
        $this->assertSame($provider->id, $fresh->metadata['provider_cancellation_user']);

        // Free window → applyProviderPenalty not called, metadata untouched.
        $this->assertArrayNotHasKey('reliability_penalty_total', $profile->fresh()->metadata ?? []);
    }

    public function test_cancel_by_provider_late_applies_penalty_to_profile(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $provider = User::factory()->create();
        $profile = ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'metadata' => ['reliability_penalty_total' => 5, 'cancellations_30d_count' => 1],
        ]);

        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:15:00', // 15min before → late, penalty applies
        ]);

        $result = app(CancelBookingService::class)->cancelByProvider($booking, $provider);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['penalty']['is_free']);
        $this->assertGreaterThan(0, $result['penalty']['penalty_eur']);
        $this->assertGreaterThan(0, $result['penalty']['reliability_penalty']);

        // Default reason text when none provided.
        $this->assertStringContainsString('non précisé', $booking->fresh()->cancellation_reason);

        // Penalty accumulated on the provider profile metadata.
        $meta = $profile->fresh()->metadata;
        $this->assertSame(5 + (int) $result['penalty']['reliability_penalty'], $meta['reliability_penalty_total']);
        $this->assertSame(2, $meta['cancellations_30d_count']);
        $this->assertArrayHasKey('last_cancellation_at', $meta);
    }

    public function test_cancel_by_provider_without_profile_skips_penalty_gracefully(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        // Provider has no ProviderProfile → applyProviderPenalty short-circuits.
        $provider = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:15:00',
        ]);

        $result = app(CancelBookingService::class)->cancelByProvider($booking, $provider);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['penalty']['is_free']);
        $this->assertSame('en_attente', $booking->fresh()->status);
    }

    // ──────────────────────────────────────────────
    // markClientNoShow
    // ──────────────────────────────────────────────

    public function test_mark_client_no_show_charges_full_fee(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 30, 0));

        $provider = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:00:00', // started 30min ago, > 15min grace
            'estimated_price' => 120,
        ]);

        $result = app(CancelBookingService::class)->markClientNoShow($booking, $provider);

        $this->assertTrue($result['ok']);
        $this->assertSame('client_no_show', $result['type']);
        $this->assertSame(120.0, $result['fee_amount']); // 100% of 120

        $fresh = $booking->fresh();
        $this->assertSame('annule', $fresh->status);
        $this->assertSame('Client no-show', $fresh->cancellation_reason);
        $this->assertSame('client', $fresh->metadata['no_show_type']);
        $this->assertSame($provider->id, $fresh->metadata['no_show_reported_by']);
        $this->assertEquals(120.0, $fresh->metadata['cancellation_fee']);
        $this->assertSame(100, $fresh->metadata['cancellation_fee_percent']);
    }

    public function test_mark_client_no_show_too_early_throws(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $provider = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00', // in the future → not a no-show
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Trop tôt');

        app(CancelBookingService::class)->markClientNoShow($booking, $provider);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function makeBooking(array $overrides = []): Booking
    {
        $client = User::factory()->create();

        return Booking::create(array_merge([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'estimated_price' => 100,
        ], $overrides));
    }
}
