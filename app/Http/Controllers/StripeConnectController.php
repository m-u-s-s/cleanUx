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

    /** Stripe injoignable ou mal configuré ne doit pas devenir une page d'erreur : le prestataire y arrive en cliquant « activer mes paiements », et une 500 lui donne à croire que son compte est cassé. */
    private function versLeParcoursStripe(StripeConnectService $service, User $user): RedirectResponse
    {
        try {
            return $this->versUneAdresse($service->onboardingLink($user));
        } catch (\Throwable $e) {
            report($e);

            return $this->versUneAdresse(
                route('employe.dashboard'),
                __("L'activation des paiements est momentanément indisponible. Réessayez plus tard ou contactez le support."),
            );
        }
    }

    /**
     * UNE REDIRECTION CONSTRUITE, PAS DEMANDEE AU CONTENEUR.
     *
     * Livewire remplace le redirecteur du conteneur pendant qu'un composant se rend et le
     * restitue en dehydratant — ce qu'un rendu interrompu (403) ne fait jamais. `redirect()`
     * renvoyait alors un `Redirector`, que le routeur ne sait pas rendre : 500 sur le chemin
     * meme dont ce controleur promet qu'il n'en produira pas.
     */
    private function versUneAdresse(string $adresse, ?string $erreur = null): RedirectResponse
    {
        $reponse = new RedirectResponse($adresse);
        $reponse->setSession(app('session.store'));

        return $erreur === null ? $reponse : $reponse->with('error', $erreur);
    }

    public function return(StripeConnectService $service): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->isEmploye(), 403);

        try {
            $service->syncAccountStatus($user);
        } catch (\Throwable $e) {
            report($e);

            return $this->versUneAdresse(
                route('employe.dashboard'),
                __('Nous n’avons pas pu confirmer votre compte de paiement. Réessayez dans quelques minutes.'),
            );
        }

        $reponse = new RedirectResponse(route('employe.dashboard'));
        $reponse->setSession(app('session.store'));

        return $reponse->with('success', 'Votre compte Stripe Connect a été vérifié.');
    }
}
