<?php

namespace App\Http\Middleware;

use App\Models\ProviderProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint les comptes prestataires créés par l'inscription en libre-service tant qu'ils n'ont
 * pas été approuvés. Ils gardent l'accès aux routes qui leur permettent de compléter leur
 * dossier — celles-ci s'excluent explicitement via `withoutMiddleware`.
 *
 * Ne vise QUE les profils portant `self_registered_at`. C'est délibéré : sur la base réelle,
 * 4 comptes prestataires sur 9 ne sont pas `active` (statut `pending`, voire aucun profil).
 * Une garde fondée sur le statut ou sur l'existence d'un profil mettrait donc dehors des
 * prestataires légitimes déjà en production. Les comptes antérieurs à ce changement n'ont pas
 * cette colonne renseignée et traversent le middleware sans condition.
 */
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

        return response()->json([
            'ok' => false,
            'error_code' => 'provider_pending_approval',
            'message' => "Votre compte prestataire est en cours de validation. Complétez votre dossier d'inscription ; l'accès complet sera ouvert après approbation.",
        ], 403);
    }
}
