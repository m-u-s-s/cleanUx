<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Payments\StripeConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class StripeConnectController extends Controller
{
    public function start(StripeConnectService $service): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->isEmploye(), 403);

        return $this->versLeParcoursStripe($service, $user);
    }

    public function refresh(StripeConnectService $service): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->isEmploye(), 403);

        return $this->versLeParcoursStripe($service, $user);
    }

    /**
     * Stripe injoignable ou mal configuré ne doit pas devenir une page d'erreur :
     * le prestataire y arrive en cliquant « activer mes paiements », et une 500
     * lui donne à croire que son compte est cassé.
     *
     * L'exception est rapportée — on veut la voir passer dans la supervision —
     * mais l'utilisateur, lui, repart avec une phrase.
     */
    private function versLeParcoursStripe(StripeConnectService $service, User $user): RedirectResponse
    {
        try {
            return redirect()->away($service->onboardingLink($user));
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('employe.dashboard')
                ->with('error', __("L'activation des paiements est momentanément indisponible. Réessayez plus tard ou contactez le support."));
        }
    }

    public function return(StripeConnectService $service): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->isEmploye(), 403);

        try {
            $service->syncAccountStatus($user);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('employe.dashboard')
                ->with('error', __('Nous n’avons pas pu confirmer votre compte de paiement. Réessayez dans quelques minutes.'));
        }

        return redirect()
            ->route('employe.dashboard')
            ->with('success', 'Votre compte Stripe Connect a été vérifié.');
    }
}
