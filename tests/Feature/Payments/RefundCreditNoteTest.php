<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\FinanceCreditNote;
use App\Models\User;
use App\Services\Payments\StripeConnectPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\TestCase;

/** Audit HIGH — un remboursement capturé génère un avoir comptable. */
class RefundCreditNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_generates_a_credit_note(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);

        $booking = Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => 'pi_fake',
            'payment_amount_cents' => 12100,
            'provider_amount_cents' => 9680,
            'currency' => 'EUR',
            'pricing_snapshot' => ['tax_rate' => 21.0],
        ]);

        Stripe::setApiKey('sk_test_fake');
        $stripe = (new FakeStripeHttpClient)->stub('POST', '/v1/refunds', [
            'id' => 're_fake_credit',
            'object' => 'refund',
            'amount' => 12100,
            'status' => 'succeeded',
        ]);
        ApiRequestor::setHttpClient($stripe);

        try {
            app(StripeConnectPaymentService::class)->refundMissionPayment($booking, null, 'requested_by_customer');
        } finally {
            ApiRequestor::setHttpClient(null);
        }

        $note = FinanceCreditNote::where('stripe_refund_id', 're_fake_credit')->first();
        $this->assertNotNull($note, 'Un avoir doit être généré pour le remboursement.');
        $this->assertSame($booking->id, $note->booking_id);
        $this->assertSame('121.00', (string) $note->amount);
    }
}
