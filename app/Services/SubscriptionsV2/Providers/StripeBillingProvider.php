<?php

namespace App\Services\SubscriptionsV2\Providers;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Services\SubscriptionsV2\BillingResult;
use App\Services\SubscriptionsV2\Contracts\BillingProviderContract;
use Illuminate\Support\Facades\Log;

/**
 * Stripe PaymentIntent driver. Squelette : nécessite un `stripe_customer_id`
 * et un payment_method par défaut sur l'utilisateur (via Cashier).
 *
 * Soft-fail systématique : toute exception est capturée et renvoyée comme
 * BillingResult(success=false). Le caller (BillingProcessor) sait quoi faire.
 */
class StripeBillingProvider implements BillingProviderContract
{
    public function name(): string
    {
        return 'stripe';
    }

    public function chargeCycle(SubscriptionCycleV2 $cycle): BillingResult
    {
        $sub = $cycle->subscription;
        try {
            // Cashier-aware customer lookup
            $user = $sub->user;
            if (! $user) {
                return new BillingResult(
                    false, $cycle->planned_amount_cents, $sub->billing_currency,
                    error: 'no_user', provider: 'stripe',
                );
            }

            $stripeCustomerId = method_exists($user, 'stripeId')
                ? $user->stripeId()
                : ($user->stripe_id ?? null);
            if (! $stripeCustomerId) {
                return new BillingResult(
                    false, $cycle->planned_amount_cents, $sub->billing_currency,
                    error: 'no_stripe_customer', provider: 'stripe',
                );
            }

            // Off-session payment intent — nécessite Stripe SDK + secret_key
            $stripeKey = (string) config('cashier.secret') ?: (string) config('services.stripe.secret');
            if (! $stripeKey || ! class_exists(\Stripe\StripeClient::class)) {
                return new BillingResult(
                    false, $cycle->planned_amount_cents, $sub->billing_currency,
                    error: 'stripe_not_configured', provider: 'stripe',
                );
            }

            $stripe = new \Stripe\StripeClient($stripeKey);
            $intent = $stripe->paymentIntents->create([
                'amount' => $cycle->planned_amount_cents,
                'currency' => strtolower($sub->billing_currency),
                'customer' => $stripeCustomerId,
                'off_session' => true,
                'confirm' => true,
                'description' => 'CleanUx subscription cycle #' . $cycle->cycle_number,
                'metadata' => [
                    'subscription_id' => $sub->id,
                    'cycle_id' => $cycle->id,
                    'subscription_code' => $sub->code,
                ],
                'capture_method' => (string) config('subscriptions_v2.stripe.capture_method', 'automatic'),
            ]);

            if (in_array($intent->status, ['succeeded', 'requires_capture'], true)) {
                return new BillingResult(
                    success: true,
                    amountCents: $cycle->planned_amount_cents,
                    currency: $sub->billing_currency,
                    reference: $intent->id,
                    raw: $intent->toArray(),
                    provider: 'stripe',
                );
            }

            return new BillingResult(
                false, $cycle->planned_amount_cents, $sub->billing_currency,
                error: 'intent_status_' . $intent->status,
                raw: $intent->toArray(),
                provider: 'stripe',
            );
        } catch (\Throwable $e) {
            Log::warning('[subscriptions_v2] stripe charge error', [
                'cycle_id' => $cycle->id, 'error' => $e->getMessage(),
            ]);
            return new BillingResult(
                false, $cycle->planned_amount_cents, $sub->billing_currency,
                error: 'exception:' . mb_substr($e->getMessage(), 0, 200),
                provider: 'stripe',
            );
        }
    }
}
