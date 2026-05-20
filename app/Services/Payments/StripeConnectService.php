<?php

namespace App\Services\Payments;

use App\Models\User;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Stripe;

class StripeConnectService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function createOrGetAccount(User $user): string
    {
        if ($user->stripe_connect_account_id) {
            return $user->stripe_connect_account_id;
        }

        $country = $user->country ?? $user->business_country ?? config('services.stripe.connect_country', 'BE');
        $account = Account::create([
            'type' => 'express',
            'country' => $country,
            'email' => $user->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'user_id' => $user->id,
                'role' => $user->role,
                'platform' => 'cleanux',
            ],
        ]);

        $user->update([
            'stripe_connect_account_id' => $account->id,
            'stripe_connect_status' => 'pending',
        ]);

        return $account->id;
    }

    public function onboardingLink(User $user): string
    {
        $accountId = $this->createOrGetAccount($user);

        $link = AccountLink::create([
            'account' => $accountId,
            'refresh_url' => config('services.stripe.connect_refresh_url') ?: url('/dashboard/stripe-connect/refresh'),
            'return_url' => config('services.stripe.connect_return_url') ?: url('/dashboard/stripe-connect/return'),
            'type' => 'account_onboarding',
        ]);

        return $link->url;
    }

    public function syncAccountStatus(User $user): void
    {
        if (! $user->stripe_connect_account_id) {
            return;
        }

        $account = Account::retrieve($user->stripe_connect_account_id);

        $chargesEnabled = (bool) $account->charges_enabled;
        $payoutsEnabled = (bool) $account->payouts_enabled;

        $user->update([
            'stripe_connect_status' => $chargesEnabled && $payoutsEnabled ? 'active' : 'pending',
            'stripe_connect_onboarded_at' => $chargesEnabled && $payoutsEnabled
                ? ($user->stripe_connect_onboarded_at ?? now())
                : null,
            'stripe_connect_charges_enabled_at' => $chargesEnabled
                ? ($user->stripe_connect_charges_enabled_at ?? now())
                : null,
            'stripe_connect_payouts_enabled_at' => $payoutsEnabled
                ? ($user->stripe_connect_payouts_enabled_at ?? now())
                : null,
        ]);
    }
}