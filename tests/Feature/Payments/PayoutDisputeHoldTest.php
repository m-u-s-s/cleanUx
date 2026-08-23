<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Audit HIGH — un litige non résolu doit GELER le payout du prestataire : tant que le litige est ouvert, payouts:process ne doit pas libérer la rémunération. */
class PayoutDisputeHoldTest extends TestCase
{
    use RefreshDatabase;

    private function completedCapturedBooking(): Booking
    {
        $client = User::factory()->client()->create();
        $employe = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $employe->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
            'status' => 'completed',
            'payout_status' => null,
            'devis_estime' => 100.00,
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => 'pi_fake_'.uniqid(),
        ]);
    }

    public function test_booking_without_dispute_is_processed(): void
    {
        $booking = $this->completedCapturedBooking();

        $this->artisan('payouts:process')->assertExitCode(0);

        $this->assertNotNull($booking->fresh()->payout_status, 'Un booking sans litige doit être payé.');
    }

    public function test_booking_with_open_dispute_is_held(): void
    {
        $booking = $this->completedCapturedBooking();
        ComplaintCase::create([
            'reference' => 'DSP-'.uniqid(),
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'subject' => 'Litige',
            'description' => 'Prestation contestée.',
            'category' => 'quality',
            'priority' => 'normal',
            'severity' => 'medium',
            'status' => ComplaintCase::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $this->artisan('payouts:process')->assertExitCode(0);

        $this->assertNull($booking->fresh()->payout_status, 'Un booking avec litige ouvert ne doit PAS être payé.');
    }

    public function test_booking_with_resolved_dispute_is_processed(): void
    {
        $booking = $this->completedCapturedBooking();
        ComplaintCase::create([
            'reference' => 'DSP-'.uniqid(),
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'subject' => 'Litige',
            'description' => 'Prestation contestée puis résolue.',
            'category' => 'quality',
            'priority' => 'normal',
            'severity' => 'medium',
            'status' => ComplaintCase::STATUS_RESOLVED,
            'last_activity_at' => now(),
        ]);

        $this->artisan('payouts:process')->assertExitCode(0);

        $this->assertNotNull($booking->fresh()->payout_status, 'Un litige résolu ne doit plus bloquer le payout.');
    }
}
