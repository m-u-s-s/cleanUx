<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Models\StripeWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Webhook Stripe Connect — hardened version (Phase Stripe v2).
 *
 * Workflow:
 *   1. Vérifier signature Stripe
 *   2. Stocker l'event en DB (firstOrCreate sur stripe_event_id → idempotence)
 *   3. Dispatch async job pour traitement (queue)
 *   4. Return 200 immédiatement (Stripe veut < 5s)
 *
 * Si la même requête arrive 2 fois (Stripe retry après timeout), le firstOrCreate
 * trouve l'event existant et ne le re-dispatch que si pas encore en cours.
 *
 * Le traitement asynchrone permet :
 *   - Retry avec backoff si DB/Stripe API échoue temporairement
 *   - Pas de timeout côté Stripe pendant qu'on fait des syncs lents
 *   - Dead letter après N tentatives pour alerter admin
 */
class StripeConnectWebhookController extends Controller
{
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
            Log::warning('StripeConnectWebhook: signature invalide', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 400);
        } catch (\Throwable $e) {
            Log::error('StripeConnectWebhook: parse error', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'parse error'], 400);
        }

        $stripeEventId = $event->id ?? null;
        $type = $event->type ?? '';

        if (! $stripeEventId) {
            return response()->json(['ok' => false, 'error' => 'event has no id'], 400);
        }

        // Idempotence : firstOrCreate sur stripe_event_id (UNIQUE index)
        $stored = StripeWebhookEvent::firstOrCreate(
            ['stripe_event_id' => $stripeEventId],
            [
                'type' => $type,
                'status' => StripeWebhookEvent::STATUS_RECEIVED,
                'payload' => json_decode($payload, true) ?: ['raw' => $payload],
                'received_at' => now(),
                'account_id' => $event->account ?? null,
            ]
        );

        $shouldDispatch = ! $stored->isTerminal()
            && $stored->status !== StripeWebhookEvent::STATUS_PROCESSING;

        if ($shouldDispatch) {
            ProcessStripeWebhookJob::dispatch($stored->id)
                ->onQueue('stripe-webhooks');

            Log::info('StripeConnectWebhook: stored + dispatched', [
                'event_id' => $stored->id,
                'stripe_event_id' => $stripeEventId,
                'type' => $type,
            ]);
        } else {
            Log::info('StripeConnectWebhook: replay détecté, skip dispatch', [
                'event_id' => $stored->id,
                'stripe_event_id' => $stripeEventId,
                'status' => $stored->status,
            ]);
        }

        return response()->json([
            'ok' => true,
            'event_id' => $stored->id,
            'status' => $stored->status,
            'dispatched' => $shouldDispatch,
        ]);
    }
}
