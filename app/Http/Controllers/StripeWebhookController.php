<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function handleCustomerSubscriptionCreated(array $payload)
    {
        $this->syncUserPlan($payload, 'active');

        return $this->successMethod();
    }

    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        $status = data_get($payload, 'data.object.status', 'inactive');

        $this->syncUserPlan($payload, $status);

        return $this->successMethod();
    }

    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $this->syncUserPlan($payload, 'cancelled');

        return $this->successMethod();
    }

    protected function syncUserPlan(array $payload, string $stripeStatus): void
    {
        $stripeCustomerId = data_get($payload, 'data.object.customer');

        if (! $stripeCustomerId) {
            return;
        }

        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if (! $user) {
            return;
        }

        $isActive = in_array($stripeStatus, ['active', 'trialing'], true);

        $user->update([
            'plan_type' => $isActive ? 'premium' : 'standard',
            'plan_status' => $stripeStatus,
            'premium_started_at' => $isActive ? ($user->premium_started_at ?? now()) : null,
            'premium_renewal_at' => $isActive ? now()->addMonth() : null,
        ]);
    }
}