<?php

namespace Tests\Feature\Finance;

use App\Models\Booking;
use App\Models\CustomerCredit;
use App\Models\User;
use App\Services\Finance\CustomerCreditApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for CustomerCreditApplicationService::applyAvailableCredits: the
 * zero-devis guard, FIFO consumption across multiple credits (full exhaust ->
 * status "used", partial draw stays active), the remaining-to-pay break path,
 * the active/remaining/expiry filters, and the booking snapshot + activity log
 * side effects.
 */
class CustomerCreditApplicationServiceCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    private function makeService(): CustomerCreditApplicationService
    {
        return app(CustomerCreditApplicationService::class);
    }

    private function seedCredit(User $client, array $overrides = []): CustomerCredit
    {
        return CustomerCredit::create(array_merge([
            'client_id' => $client->id,
            'type' => 'commercial_gesture',
            'amount' => 50.0,
            'remaining_amount' => 50.0,
            'status' => 'active',
            'reason' => 'seed',
        ], $overrides));
    }

    public function test_returns_zero_when_devis_is_not_positive(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 0]);
        $client = $booking->client;

        $credit = $this->seedCredit($client, ['amount' => 40.0, 'remaining_amount' => 40.0]);

        $applied = $this->makeService()->applyAvailableCredits($client, $booking);

        $this->assertSame(0.0, (float) $applied);
        $this->assertEqualsWithDelta(40.0, (float) $credit->fresh()->remaining_amount, 0.001);
    }

    public function test_consumes_credits_fifo_exhausting_first_and_partially_drawing_second(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 100.0]);
        $client = $booking->client;

        $first = $this->seedCredit($client, [
            'amount' => 30.0,
            'remaining_amount' => 30.0,
            'created_at' => now()->subDays(3),
        ]);
        $second = $this->seedCredit($client, [
            'amount' => 90.0,
            'remaining_amount' => 90.0,
            'created_at' => now()->subDay(),
        ]);

        $applied = $this->makeService()->applyAvailableCredits($client, $booking);

        $this->assertEqualsWithDelta(100.0, (float) $applied, 0.001);

        $first->refresh();
        $this->assertSame('used', $first->status);
        $this->assertEqualsWithDelta(0.0, (float) $first->remaining_amount, 0.001);

        $second->refresh();
        $this->assertSame('active', $second->status);
        $this->assertEqualsWithDelta(20.0, (float) $second->remaining_amount, 0.001);

        $booking->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $booking->devis_estime, 0.001);

        $snapshot = (array) $booking->pricing_snapshot;
        $this->assertEqualsWithDelta(100.0, (float) $snapshot['customer_credit_applied'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $snapshot['devis_after_credit'], 0.001);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'customer_credit_applied_to_booking',
        ]);
    }

    public function test_stops_once_remaining_to_pay_is_covered_and_leaves_later_credit_untouched(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 20.0]);
        $client = $booking->client;

        $first = $this->seedCredit($client, [
            'amount' => 50.0,
            'remaining_amount' => 50.0,
            'created_at' => now()->subDays(2),
        ]);
        $later = $this->seedCredit($client, [
            'amount' => 30.0,
            'remaining_amount' => 30.0,
            'created_at' => now(),
        ]);

        $applied = $this->makeService()->applyAvailableCredits($client, $booking);

        $this->assertEqualsWithDelta(20.0, (float) $applied, 0.001);

        $first->refresh();
        $this->assertSame('active', $first->status);
        $this->assertEqualsWithDelta(30.0, (float) $first->remaining_amount, 0.001);

        $later->refresh();
        $this->assertEqualsWithDelta(30.0, (float) $later->remaining_amount, 0.001);

        $booking->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $booking->devis_estime, 0.001);
    }

    public function test_ignores_non_active_zero_and_expired_credits_and_applies_only_eligible_ones(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 200.0]);
        $client = $booking->client;

        $this->seedCredit($client, ['status' => 'used', 'remaining_amount' => 25.0]);
        $this->seedCredit($client, ['status' => 'active', 'remaining_amount' => 0.0]);
        $this->seedCredit($client, ['status' => 'active', 'remaining_amount' => 40.0, 'expires_at' => now()->subDay()]);

        $futureExpiry = $this->seedCredit($client, [
            'amount' => 35.0,
            'remaining_amount' => 35.0,
            'expires_at' => now()->addDays(5),
            'created_at' => now()->subDays(2),
        ]);
        $neverExpires = $this->seedCredit($client, [
            'amount' => 15.0,
            'remaining_amount' => 15.0,
            'expires_at' => null,
            'created_at' => now()->subDay(),
        ]);

        $applied = $this->makeService()->applyAvailableCredits($client, $booking);

        $this->assertEqualsWithDelta(50.0, (float) $applied, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $futureExpiry->fresh()->remaining_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $neverExpires->fresh()->remaining_amount, 0.001);

        $booking->refresh();
        $this->assertEqualsWithDelta(150.0, (float) $booking->devis_estime, 0.001);
    }

    public function test_returns_zero_when_client_has_no_eligible_credits(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 75.0]);
        $client = $booking->client;

        $this->seedCredit($client, ['status' => 'cancelled', 'remaining_amount' => 60.0]);

        $applied = $this->makeService()->applyAvailableCredits($client, $booking);

        $this->assertSame(0.0, (float) $applied);

        $booking->refresh();
        $this->assertEqualsWithDelta(75.0, (float) $booking->devis_estime, 0.001);
    }
}
