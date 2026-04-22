<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    /**
     * checkout.session.completed
     */
    public function handleCheckoutSessionCompleted(array $payload)
    {
        $stripeCustomerId = data_get($payload, 'data.object.customer');

        if (! $stripeCustomerId) {
            return $this->successMethod();
        }

        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if (! $user) {
            return $this->successMethod();
        }

        $this->syncPremiumStatus($user, 'active');

        return $this->successMethod();
    }

    /**
     * customer.subscription.created
     */
    public function handleCustomerSubscriptionCreated(array $payload)
    {
        $stripeCustomerId = data_get($payload, 'data.object.customer');

        if ($stripeCustomerId) {
            $user = User::where('stripe_id', $stripeCustomerId)->first();

            if ($user) {
                $this->syncPremiumStatus($user, data_get($payload, 'data.object.status', 'active'), $payload);
            }
        }

        return $this->successMethod();
    }

    /**
     * customer.subscription.updated
     */
    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        $stripeCustomerId = data_get($payload, 'data.object.customer');

        if ($stripeCustomerId) {
            $user = User::where('stripe_id', $stripeCustomerId)->first();

            if ($user) {
                $this->syncPremiumStatus($user, data_get($payload, 'data.object.status', 'active'), $payload);
            }
        }

        return $this->successMethod();
    }

    /**
     * customer.subscription.deleted
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $stripeCustomerId = data_get($payload, 'data.object.customer');

        if ($stripeCustomerId) {
            $user = User::where('stripe_id', $stripeCustomerId)->first();

            if ($user) {
                $user->update([
                    'plan_type' => 'standard',
                    'plan_status' => 'cancelled',
                    'premium_renewal_at' => null,
                ]);
            }
        }

        return $this->successMethod();
    }

    /**
     * invoice.payment_failed
     */
    public function handleInvoicePaymentFailed(array $payload)
    {
        $stripeCustomerId = data_get($payload, 'data.object.customer');

        if ($stripeCustomerId) {
            $user = User::where('stripe_id', $stripeCustomerId)->first();

            if ($user && $user->plan_type === 'premium') {
                $user->update([
                    'plan_status' => 'past_due',
                ]);
            }
        }

        return $this->successMethod();
    }

    /**
     * invoice.payment_succeeded
     */
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        $stripeCustomerId = data_get($payload, 'data.object.customer');

        if ($stripeCustomerId) {
            $user = User::where('stripe_id', $stripeCustomerId)->first();

            if ($user) {
                $this->syncPremiumStatus($user, 'active', $payload);
            }
        }

        return $this->successMethod();
    }

    protected function syncPremiumStatus(User $user, string $stripeStatus, ?array $payload = null): void
    {
        $periodEnd = data_get($payload, 'data.object.current_period_end');

        $mappedStatus = match ($stripeStatus) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => 'past_due',
            'canceled' => 'cancelled',
            default => 'inactive',
        };

        if ($mappedStatus === 'cancelled') {
            $user->update([
                'plan_type' => 'standard',
                'plan_status' => 'cancelled',
                'premium_renewal_at' => null,
            ]);

            return;
        }

        $user->update([
            'plan_type' => 'premium',
            'plan_status' => $mappedStatus,
            'premium_started_at' => $user->premium_started_at ?? now(),
            'premium_renewal_at' => $periodEnd ? now()->setTimestamp((int) $periodEnd) : $user->premium_renewal_at,
        ]);
    }
}