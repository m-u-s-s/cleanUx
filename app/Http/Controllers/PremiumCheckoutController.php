<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PremiumCheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $user = Auth::user();

        if (! $user || ! $user->isClient()) {
            abort(403);
        }

        if ($user->isPremium()) {
            return redirect()
                ->route('premium.offer')
                ->with('success', 'Vous êtes déjà client Premium.');
        }

        return $user->newSubscription('default', env('STRIPE_PREMIUM_PRICE_ID'))
            ->checkout([
                'success_url' => route('premium.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('premium.cancel'),
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_type' => 'premium',
                ],
            ]);
    }

    public function success(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $subscription = $user->subscription('default');

        if ($subscription && $subscription->valid()) {
            $user->update([
                'plan_type' => 'premium',
                'plan_status' => 'active',
                'premium_started_at' => now(),
                'premium_renewal_at' => now()->addMonth(),
            ]);
        }

        return redirect()
            ->route('client.dashboard')
            ->with('success', 'Votre abonnement Premium est activé.');
    }

    public function cancel()
    {
        return redirect()
            ->route('premium.offer')
            ->with('error', 'Le paiement a été annulé.');
    }
}