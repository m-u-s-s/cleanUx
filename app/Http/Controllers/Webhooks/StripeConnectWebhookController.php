<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\User;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

/**
 * Phase 13 — Webhook Stripe Connect (séparé du webhook Cashier subscription).
 *
 * Routes (à déclarer dans routes/web.php — sans middleware web !) :
 *   POST /webhooks/stripe-connect
 *
 * Sécurité : signature Stripe vérifiée via STRIPE_CONNECT_WEBHOOK_SECRET
 * (différent du webhook subscription Cashier).
 *
 * Events gérés :
 *   - account.updated                  → sync account verification status
 *   - payout.paid                      → marque ProviderPayout en paid
 *   - payout.failed                    → marque ProviderPayout en failed
 *   - charge.refunded                  → marque Booking en refunded
 *   - payment_intent.succeeded         → re-sync booking
 *   - payment_intent.payment_failed    → marque Booking failed
 *
 * Tous les autres events sont silencieusement ignorés (200 OK).
 */
class StripeConnectWebhookController extends Controller
{
    public function __construct(
        protected StripeConnectService $connectService,
        protected StripeConnectPaymentService $paymentService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.connect_webhook_secret')
               ?: env('STRIPE_CONNECT_WEBHOOK_SECRET');

        if (empty($secret)) {
            Log::warning('StripeConnectWebhook: STRIPE_CONNECT_WEBHOOK_SECRET non configuré');
            return response()->json(['ok' => false, 'error' => 'webhook secret missing'], 500);
        }

        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sig, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('StripeConnectWebhook: signature invalide', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 400);
        } catch (\Throwable $e) {
            Log::error('StripeConnectWebhook: parse error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'parse error'], 400);
        }

        $type = $event->type ?? '';
        $data = $event->data->object ?? null;

        Log::info('StripeConnectWebhook received', [
            'type'  => $type,
            'id'    => $event->id ?? null,
        ]);

        // Dispatch sur la bonne méthode selon l'event type
        try {
            match (true) {
                $type === 'account.updated'                  => $this->handleAccountUpdated($data),
                $type === 'payout.paid'                      => $this->handlePayoutPaid($data),
                $type === 'payout.failed'                    => $this->handlePayoutFailed($data),
                $type === 'charge.refunded'                  => $this->handleChargeRefunded($data),
                $type === 'payment_intent.succeeded'         => $this->handlePaymentIntentSucceeded($data),
                $type === 'payment_intent.payment_failed'    => $this->handlePaymentIntentFailed($data),
                default => null, // ignore other events
            };
        } catch (\Throwable $e) {
            Log::error('StripeConnectWebhook: handler exception', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
            // On retourne 200 pour éviter le retry Stripe — l'erreur est dans les logs
        }

        return response()->json(['ok' => true, 'handled' => $type]);
    }

    // ──────────────────────────────────────────────
    // Handlers par event type
    // ──────────────────────────────────────────────

    protected function handleAccountUpdated($account): void
    {
        if (! $account || ! isset($account->id)) return;

        $user = User::where('stripe_connect_account_id', $account->id)->first();
        if (! $user) return;

        // Re-sync via le service existant
        $this->connectService->syncAccountStatus($user);

        Log::info('StripeConnectWebhook: account.updated synced', [
            'user_id'    => $user->id,
            'account_id' => $account->id,
        ]);
    }

    protected function handlePayoutPaid($payout): void
    {
        if (! $payout || ! isset($payout->id)) return;

        $payoutModel = ProviderPayout::where('provider_payout_id', $payout->id)->first();

        if (! $payoutModel) {
            // Peut arriver si le payout Stripe regroupe plusieurs nos payouts
            // ou si on n'a pas encore créé l'entrée. On log et on passe.
            Log::info('StripeConnectWebhook: payout.paid sans match local', [
                'stripe_payout_id' => $payout->id,
            ]);
            return;
        }

        $payoutModel->markAsPaid($payout->id);

        Log::info('StripeConnectWebhook: payout.paid marqué', [
            'payout_id' => $payoutModel->id,
        ]);
    }

    protected function handlePayoutFailed($payout): void
    {
        if (! $payout || ! isset($payout->id)) return;

        $payoutModel = ProviderPayout::where('provider_payout_id', $payout->id)->first();
        if (! $payoutModel) return;

        $payoutModel->markAsFailed([
            'failure_code'    => $payout->failure_code ?? null,
            'failure_message' => $payout->failure_message ?? null,
        ]);

        Log::info('StripeConnectWebhook: payout.failed marqué', [
            'payout_id'     => $payoutModel->id,
            'failure_code'  => $payout->failure_code ?? null,
        ]);
    }

    protected function handleChargeRefunded($charge): void
    {
        if (! $charge || ! isset($charge->payment_intent)) return;

        $booking = Booking::where('stripe_payment_intent_id', $charge->payment_intent)->first();
        if (! $booking) return;

        $isTotal = (int) ($charge->amount_refunded ?? 0) >= (int) ($charge->amount ?? 0);

        $booking->update([
            'payment_status'   => $isTotal ? 'refunded' : 'partially_refunded',
            'payment_refunded_at' => now(),
        ]);

        Log::info('StripeConnectWebhook: charge.refunded synced', [
            'booking_id' => $booking->id,
            'is_total'   => $isTotal,
        ]);
    }

    protected function handlePaymentIntentSucceeded($intent): void
    {
        if (! $intent || ! isset($intent->id)) return;

        $booking = Booking::where('stripe_payment_intent_id', $intent->id)->first();
        if (! $booking) return;

        $this->paymentService->syncPaymentIntent($booking);
    }

    protected function handlePaymentIntentFailed($intent): void
    {
        if (! $intent || ! isset($intent->id)) return;

        $booking = Booking::where('stripe_payment_intent_id', $intent->id)->first();
        if (! $booking) return;

        $booking->update([
            'payment_status'    => 'failed',
            'payment_failed_at' => now(),
        ]);

        Log::info('StripeConnectWebhook: payment_intent.payment_failed', [
            'booking_id' => $booking->id,
        ]);
    }
}
