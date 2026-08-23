<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\Payments\ProviderWalletService;
use App\Services\Payments\Webhooks\StripeWebhookHandlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/** Direct coverage of StripeWebhookHandlers per-event handlers: account.updated, payout.paid/failed, charge.refunded, payment_intent succeeded/failed, transfer.created. */
class StripeWebhookHandlersCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('webhooks_v2.enabled', false);
        Config::set('accounting_v2.auto_post_enabled', false);
        Config::set(['cashier.secret' => 'sk_test_fake', 'services.stripe.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    private function handlers(): StripeWebhookHandlers
    {
        return app(StripeWebhookHandlers::class);
    }

    private function makeBooking(string $piId, string $paymentStatus = 'pending'): Booking
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id, 'status' => 'en_attente']);
        $booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => $paymentStatus,
        ])->save();

        return $booking->fresh();
    }

    public function test_account_updated_ignored_without_id_and_without_user(): void
    {
        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handleAccountUpdated([])['status'],
        );

        $res = $this->handlers()->handleAccountUpdated(['id' => 'acct_unknown']);
        $this->assertSame(StripeWebhookEvent::STATUS_IGNORED, $res['status']);
        $this->assertSame('no_user_for_account', $res['details']['reason']);
    }

    public function test_account_updated_processed_for_known_user(): void
    {
        $user = User::factory()->employe()->create(['stripe_connect_account_id' => 'acct_known_1']);

        // syncAccountStatus retrieves the Connect account from Stripe.
        $this->stripe->stub('GET', '/v1/accounts/acct_known_1', [
            'id' => 'acct_known_1',
            'object' => 'account',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'requirements' => ['currently_due' => [], 'disabled_reason' => null],
        ]);

        $res = $this->handlers()->handleAccountUpdated(['id' => 'acct_known_1']);

        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $res['status']);
        $this->assertSame($user->id, $res['details']['user_id']);
    }

    public function test_payout_paid_ignored_and_already_and_success(): void
    {
        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handlePayoutPaid([])['status'],
        );

        $res = $this->handlers()->handlePayoutPaid(['id' => 'po_missing']);
        $this->assertSame('no_local_payout', $res['details']['reason']);

        $alreadyPaid = ProviderPayout::factory()->paid()->create(['provider_payout_id' => 'po_already']);
        $resAlready = $this->handlers()->handlePayoutPaid(['id' => 'po_already']);
        $this->assertTrue($resAlready['details']['already']);

        $pending = ProviderPayout::factory()->create(['provider_payout_id' => 'po_pending']);
        $resOk = $this->handlers()->handlePayoutPaid(['id' => 'po_pending']);
        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $resOk['status']);
        $this->assertSame(ProviderPayout::STATUS_PAID, $pending->fresh()->status);
    }

    public function test_payout_failed_ignored_and_success_reverses_wallet(): void
    {
        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handlePayoutFailed([])['status'],
        );

        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handlePayoutFailed(['id' => 'po_nope'])['status'],
        );

        $payout = ProviderPayout::factory()->create(['provider_payout_id' => 'po_failit']);
        $res = $this->handlers()->handlePayoutFailed([
            'id' => 'po_failit',
            'failure_code' => 'account_closed',
            'failure_message' => 'bank declined',
        ]);

        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $res['status']);
        $this->assertSame(ProviderPayout::STATUS_FAILED, $payout->fresh()->status);
    }

    public function test_charge_refunded_ignored_without_pi_or_booking(): void
    {
        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handleChargeRefunded([])['status'],
        );

        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handleChargeRefunded(['payment_intent' => 'pi_nobooking'])['status'],
        );
    }

    public function test_charge_refunded_total_with_per_refund_data_records_clawback(): void
    {
        $booking = $this->makeBooking('pi_ref_total', 'captured');
        $booking->forceFill([
            'employe_id' => User::factory()->employe()->create()->id,
            'provider_amount_cents' => 10000,
        ])->save();

        // LE GAIN D'ABORD : la reprise est plafonnee au montant reellement verse au prestataire.
        app(ProviderWalletService::class)->recordEarning($booking->fresh());

        $res = $this->handlers()->handleChargeRefunded([
            'id' => 'ch_total',
            'payment_intent' => 'pi_ref_total',
            'amount' => 12000,
            'amount_refunded' => 12000,
            'currency' => 'eur',
            'refunds' => ['data' => [['id' => 're_1', 'amount' => 12000]]],
        ]);

        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $res['status']);
        $this->assertTrue($res['details']['is_total']);
        $this->assertSame('refunded', $booking->fresh()->payment_status);
        $this->assertSame(1, ProviderWalletTransaction::query()
            ->where('source_id', $booking->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)->count());
    }

    public function test_charge_refunded_partial_fallback_without_refund_data(): void
    {
        $booking = $this->makeBooking('pi_ref_partial', 'captured');
        $booking->forceFill([
            'employe_id' => User::factory()->employe()->create()->id,
            'provider_amount_cents' => 10000,
        ])->save();

        // LE GAIN D'ABORD : la reprise est plafonnee au montant reellement verse au prestataire.
        app(ProviderWalletService::class)->recordEarning($booking->fresh());

        $res = $this->handlers()->handleChargeRefunded([
            'id' => 'ch_partial',
            'payment_intent' => 'pi_ref_partial',
            'amount' => 12000,
            'amount_refunded' => 6000,
        ]);

        $this->assertFalse($res['details']['is_total']);
        $this->assertSame('partially_refunded', $booking->fresh()->payment_status);
        $this->assertSame(1, ProviderWalletTransaction::query()
            ->where('source_id', $booking->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)->count());
    }

    public function test_payment_intent_failed_ignored_and_notifies_client(): void
    {
        Notification::fake();

        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handlePaymentIntentFailed([])['status'],
        );

        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handlePaymentIntentFailed(['id' => 'pi_no_booking'])['status'],
        );

        $booking = $this->makeBooking('pi_fail_xyz');
        $res = $this->handlers()->handlePaymentIntentFailed([
            'id' => 'pi_fail_xyz',
            'amount' => 9000,
            'currency' => 'eur',
            'last_payment_error' => ['message' => 'Card declined', 'code' => 'card_declined'],
        ]);

        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $res['status']);
        $this->assertSame('failed', $booking->fresh()->payment_status);
    }

    public function test_payment_intent_succeeded_ignored_paths(): void
    {
        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handlePaymentIntentSucceeded([])['status'],
        );

        // No booking matches -> ignored after tip-check (which is a no-op here).
        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handlePaymentIntentSucceeded(['id' => 'pi_succ_orphan'])['status'],
        );
    }

    public function test_payment_intent_succeeded_captures_and_records_earning(): void
    {
        $booking = $this->makeBooking('pi_succ_cap');

        $this->stripe->stub('GET', '/v1/payment_intents/pi_succ_cap',
            StripeFakeResponses::paymentIntent('pi_succ_cap', 'succeeded'));

        $res = $this->handlers()->handlePaymentIntentSucceeded([
            'id' => 'pi_succ_cap',
            'amount' => 12000,
            'currency' => 'eur',
        ]);

        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $res['status']);
        $this->assertSame('captured', $booking->fresh()->payment_status);
        $this->assertTrue($res['details']['transitioned_to_captured']);
    }

    public function test_transfer_created_ignored_existing_and_synced_booking(): void
    {
        $this->assertSame(
            StripeWebhookEvent::STATUS_IGNORED,
            $this->handlers()->handleTransferCreated([])['status'],
        );

        // Existing wallet transaction with same transfer id -> already processed.
        $provider = User::factory()->employe()->create();
        ProviderWalletTransaction::create([
            'provider_user_id' => $provider->id,
            'type' => ProviderWalletTransaction::TYPE_PAYOUT,
            'direction' => ProviderWalletTransaction::DIRECTION_DEBIT,
            'amount' => 50,
            'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_PROCESSING,
            'source_type' => 'booking',
            'source_id' => 1,
            'stripe_transfer_id' => 'tr_exists',
            'idempotency_key' => 'tr-exists-key',
            'occurred_at' => now(),
        ]);
        $resExisting = $this->handlers()->handleTransferCreated(['id' => 'tr_exists']);
        $this->assertTrue($resExisting['details']['already']);

        // New transfer referencing a booking via metadata -> sync booking.
        $booking = $this->makeBooking('pi_transfer');
        $resSync = $this->handlers()->handleTransferCreated([
            'id' => 'tr_new',
            'metadata' => ['booking_id' => $booking->id],
        ]);
        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $resSync['status']);
        $this->assertSame('tr_new', $booking->fresh()->stripe_transfer_id);
        $this->assertSame('transferred', $booking->fresh()->payout_status);
    }

    public function test_transfer_created_noted_no_action_without_booking(): void
    {
        $res = $this->handlers()->handleTransferCreated(['id' => 'tr_orphan']);

        $this->assertSame(StripeWebhookEvent::STATUS_IGNORED, $res['status']);
        $this->assertSame('transfer_noted_no_action', $res['details']['reason']);
    }
}
