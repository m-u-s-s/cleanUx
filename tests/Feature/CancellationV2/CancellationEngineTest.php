<?php

namespace Tests\Feature\CancellationV2;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\CancellationPolicy;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancellationEngineTest extends TestCase
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

    protected function makeBooking(User $client, ?\DateTimeInterface $scheduledAt = null, float $amount = 100.0): Booking
    {
        $scheduledAt ??= now()->addDays(3);
        return Booking::create([
            'client_id' => $client->id,
            'date' => $scheduledAt,
            'heure' => $scheduledAt->format('H:i'),
            'scheduled_at' => $scheduledAt,
            'status' => 'confirme',
            'devis_estime' => $amount,
        ]);
    }

    public function test_quote_with_more_than_48_hours_is_free_for_client(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, now()->addHours(72), 100.0);

        $quote = app(CancellationEngine::class)->quote($booking->id, 'client');

        $this->assertSame(0.0, $quote->feePercent);
        $this->assertSame(0, $quote->feeAmountCents);
        $this->assertSame(10000, $quote->refundAmountCents);
    }

    public function test_quote_within_24_to_48_hours_applies_25_percent_fee(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, now()->addHours(30), 100.0);

        $quote = app(CancellationEngine::class)->quote($booking->id, 'client');

        $this->assertEqualsWithDelta(25.0, $quote->feePercent, 0.01);
        $this->assertSame(2500, $quote->feeAmountCents);
        $this->assertSame(7500, $quote->refundAmountCents);
    }

    public function test_quote_within_2_hours_applies_100_percent_fee(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, now()->addHour(), 100.0);

        $quote = app(CancellationEngine::class)->quote($booking->id, 'client');

        $this->assertEqualsWithDelta(100.0, $quote->feePercent, 0.01);
        $this->assertSame(10000, $quote->feeAmountCents);
        $this->assertSame(0, $quote->refundAmountCents);
    }

    public function test_quote_exempt_reason_waives_fee(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, now()->addHour(), 100.0);

        $quote = app(CancellationEngine::class)->quote($booking->id, 'client', reasonCode: 'medical_emergency');

        $this->assertTrue($quote->exemptApplied);
        $this->assertSame(0, $quote->feeAmountCents);
        $this->assertSame(10000, $quote->refundAmountCents);
    }

    public function test_provider_cancellation_applies_flat_plus_percent(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, now()->addHour(), 100.0);

        $quote = app(CancellationEngine::class)->quote($booking->id, 'provider');

        // Provider <2h tier: fee_flat=3000c + fee_percent=25% × 10000 = 2500 → total 5500
        $this->assertSame(5500, $quote->feeAmountCents);
        $this->assertSame(4500, $quote->refundAmountCents);
    }

    public function test_execute_persists_cancellation_and_updates_booking_status(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, now()->addDays(3), 100.0);

        $row = app(CancellationEngine::class)->execute(
            bookingId: $booking->id,
            actor: $client,
            actorRole: 'client',
        );

        $this->assertInstanceOf(BookingCancellationV2::class, $row);
        $this->assertSame($booking->id, (int) $row->booking_id);
        $this->assertSame('client', $row->actor_role);
        $this->assertSame(0, $row->fee_amount_cents);

        $booking->refresh();
        $this->assertSame('annule', $booking->status);
    }

    public function test_execute_is_idempotent_with_same_actor(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);

        $svc = app(CancellationEngine::class);
        $a = $svc->execute($booking->id, $client, 'client');
        $b = $svc->execute($booking->id, $client, 'client');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, BookingCancellationV2::count());
    }

    public function test_execute_rejects_invalid_actor_role(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);

        $this->expectException(ValidationException::class);
        app(CancellationEngine::class)->execute($booking->id, $client, 'pirate');
    }

    public function test_execute_rejects_unknown_booking(): void
    {
        $client = User::factory()->client()->create();
        $this->expectException(ValidationException::class);
        app(CancellationEngine::class)->execute(99999, $client, 'client');
    }

    public function test_override_waives_fee_and_increases_refund(): void
    {
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $booking = $this->makeBooking($client, now()->addHour(), 100.0);

        $row = app(CancellationEngine::class)->execute($booking->id, $client, 'client');
        $this->assertSame(10000, $row->fee_amount_cents);

        $overridden = app(CancellationEngine::class)->override($row, $admin, 'Force majeure exceptional override.');

        $this->assertSame(0, (int) $overridden->fee_amount_cents);
        $this->assertSame(10000, (int) $overridden->refund_amount_cents);
        $this->assertSame($admin->id, (int) $overridden->override_admin_user_id);
        $this->assertTrue((bool) $overridden->exempt_applied);
    }

    public function test_override_rejects_short_reason(): void
    {
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $booking = $this->makeBooking($client);

        $row = app(CancellationEngine::class)->execute($booking->id, $client, 'client');
        $this->expectException(ValidationException::class);
        app(CancellationEngine::class)->override($row, $admin, 'short');
    }
}
