<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Services\Country\CountryConfigService;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Payout;
use Stripe\Stripe;

class StripeConnectService
{
    public function __construct(
        private readonly CountryConfigService $countryConfig = new CountryConfigService,
    ) {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function createOrGetAccount(User $user): string
    {
        if ($user->stripe_connect_account_id) {
            return $user->stripe_connect_account_id;
        }

        $rawCountry = $user->country ?? $user->business_country ?? config('services.stripe.connect_country', 'BE');
        $country = $this->countryConfig->getStripeCountry($rawCountry);
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
                'role' => $user->platform_role ?? null,
                'platform' => 'brio',
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

    // ──────────────────────────────────────────────────────────────
    // Sprint 0 — RN Provider API methods
    // ──────────────────────────────────────────────────────────────

    /**
     * Retrieve a Stripe Connect account by ID.
     *
     * @return object Stripe\Account in production; may be any object in tests.
     */
    public function retrieveAccount(string $accountId): object
    {
        return Account::retrieve($accountId);
    }

    /**
     * Create an Express account for a provider and store the account ID on the user.
     * Returns the new account ID.
     */
    public function createExpressAccount(User $user): string
    {
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
                'role' => $user->platform_role ?? null,
                'platform' => 'brio',
            ],
        ]);

        $user->update([
            'stripe_connect_account_id' => $account->id,
            'stripe_connect_status' => 'pending',
        ]);

        return $account->id;
    }

    /**
     * Create an account onboarding link for the given Stripe account ID.
     *
     * @return object Stripe\AccountLink in production; may be any object in tests.
     */
    public function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): object
    {
        return AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }

    /**
     * List Stripe payouts for a connected account.
     *
     * @return object Stripe\Collection in production; may be any object in tests.
     */
    public function listPayouts(string $accountId, int $limit = 20, ?string $startingAfter = null): object
    {
        $params = ['limit' => $limit];
        if ($startingAfter) {
            $params['starting_after'] = $startingAfter;
        }

        return Payout::all($params, ['stripe_account' => $accountId]);
    }

    /**
     * Create a Stripe Express dashboard login link for a connected account.
     *
     * @return object Stripe\LoginLink in production; may be any object in tests.
     */
    public function createLoginLink(string $accountId): object
    {
        return Account::createLoginLink($accountId);
    }
}
