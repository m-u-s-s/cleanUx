<?php

namespace App\Http\Middleware;

use App\Models\ProviderProfile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Routeur;
use Symfony\Component\HttpFoundation\Response;

/** Restreint les comptes prestataires créés par l'inscription en libre-service tant qu'ils n'ont pas été approuvés. */
class EnsureProviderIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $profile = ProviderProfile::query()
            ->where('user_id', $user->id)
            ->first();

        // Pas de profil, ou profil antérieur à l'inscription en libre-service : hors périmètre.
        if (! $profile || $profile->self_registered_at === null) {
            return $next($request);
        }

        if ($profile->status === 'active') {
            return $next($request);
        }

        return $this->refus($request);
    }

    private function refus(Request $request): Response
    {
        $message = "Votre compte prestataire est en cours de validation. Complétez votre dossier d'inscription ; l'accès complet sera ouvert après approbation.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'error_code' => 'provider_pending_approval',
                'message' => $message,
            ], 403);
        }

        // Le repli sur `home` n'est pas décoratif : si le parcours de vérification web disparaît ou est renommé, une redirection vers une route inexistante lèverait une RouteNotFoundException — un 500 à la place d'une explication, sur la première page que voit un nouveau prestataire.
        $cible = Routeur::has('provider.onboarding') ? 'provider.onboarding' : 'home';

        return redirect()->route($cible)->with('warning', $message);
    }
}
