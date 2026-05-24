<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 0 — Task 3 : Provider Stripe Connect API (RN Phase 2)
 *
 * GET  /api/provider/stripe-connect/status         → onboarding state + capabilities
 * POST /api/provider/stripe-connect/onboard        → create Express account + return account link URL
 * GET  /api/provider/stripe-connect/payouts        → paginated Stripe payouts list
 * GET  /api/provider/stripe-connect/dashboard-link → Stripe Express login link
 */
class StripeConnectController extends Controller
{
    public function __construct(private StripeConnectService $stripeConnect) {}

    // ──────────────────────────────────────────────
    // GET /api/provider/stripe-connect/status
    // ──────────────────────────────────────────────

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        if (! $user->stripe_connect_account_id) {
            return response()->json([
                'onboarded'        => false,
                'charges_enabled'  => false,
                'payouts_enabled'  => false,
                'requirements'     => [],
            ]);
        }

        $account = $this->stripeConnect->retrieveAccount($user->stripe_connect_account_id);

        return response()->json([
            'onboarded'        => (bool) ($account->charges_enabled && $account->payouts_enabled),
            'charges_enabled'  => (bool) $account->charges_enabled,
            'payouts_enabled'  => (bool) $account->payouts_enabled,
            'requirements'     => $account->requirements->currently_due ?? [],
            'capabilities'     => $account->capabilities ?? null,
        ]);
    }

    // ──────────────────────────────────────────────
    // POST /api/provider/stripe-connect/onboard
    // ──────────────────────────────────────────────

    public function onboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        if (! $user->stripe_connect_account_id) {
            $accountId = $this->stripeConnect->createExpressAccount($user);
            $user->refresh();
        } else {
            $accountId = $user->stripe_connect_account_id;
        }

        $refreshUrl = config('services.stripe.connect_refresh_url')
            ?: url('/dashboard/stripe-connect/refresh');
        $returnUrl  = config('services.stripe.connect_return_url')
            ?: url('/dashboard/stripe-connect/return');

        $link = $this->stripeConnect->createAccountLink($accountId, $refreshUrl, $returnUrl);

        return response()->json([
            'url'        => $link->url,
            'expires_at' => $link->expires_at,
        ]);
    }

    // ──────────────────────────────────────────────
    // GET /api/provider/stripe-connect/payouts
    // ──────────────────────────────────────────────

    public function payouts(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        if (! $user->stripe_connect_account_id) {
            return response()->json(['data' => [], 'has_more' => false]);
        }

        $limit         = min((int) ($request->query('limit', 20)), 100);
        $startingAfter = $request->query('starting_after');

        $result = $this->stripeConnect->listPayouts(
            $user->stripe_connect_account_id,
            $limit,
            $startingAfter ?: null,
        );

        return response()->json([
            'data'     => $result->data,
            'has_more' => $result->has_more,
        ]);
    }

    // ──────────────────────────────────────────────
    // GET /api/provider/stripe-connect/dashboard-link
    // ──────────────────────────────────────────────

    public function dashboardLink(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        abort_unless(
            (bool) $user->stripe_connect_account_id,
            404,
            'Compte Stripe Connect non configuré.'
        );

        $loginLink = $this->stripeConnect->createLoginLink($user->stripe_connect_account_id);

        return response()->json([
            'url' => $loginLink->url,
        ]);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function abortIfNotProvider($user): void
    {
        abort_unless(
            $user && ($user->providerProfile || $user->isEmploye()),
            403,
            'Vous devez être prestataire pour utiliser ces endpoints.'
        );
    }
}
