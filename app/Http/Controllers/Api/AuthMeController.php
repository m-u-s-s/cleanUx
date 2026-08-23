<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Support\Mobile\AppAudience;
use App\Support\Organizations\ContratDeRoleMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/auth/me Returns the currently authenticated user.
 *
 * @group Authentication
 *
 * @authenticated
 */
class AuthMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // La reprise de session est le SECOND verrou de la séparation des deux applications.
        $app = AppAudience::declared($request);

        if (! AppAudience::allows($user, $app)) {
            return response()->json(AppAudience::refusal($user, (string) $app) + ['ok' => false], 403);
        }

        // SP2 — expose le flag premium pour piloter la sélection prestataire côté
        // mobile (parité web). Sérialisé par-dessus l'utilisateur sans le muter.
        $payload = $user->toArray();
        $profile = $user->customerProfile;
        $payload['is_premium'] = $profile instanceof CustomerProfile && $profile->isPremium();

        // La reprise de session doit dire la même chose que la connexion.
        $payload['is_admin'] = method_exists($user, 'isAdmin') && $user->isAdmin();

        // La casquette prestataire, pour la même raison — et pour une de plus.
        $payload['is_provider'] = method_exists($user, 'isProvider') && $user->isProvider();

        // La réponse porte les DEUX formes, et c'est délibéré.
        // LA CASQUETTE SOCIÉTÉ, POUR LA MÊME RAISON QUE `is_admin`.
        $payload['is_entreprise'] = method_exists($user, 'isEntreprise') && $user->isEntreprise();

        // L'ORGANISATION SE RÉSOUT PAR `organizationContextId()`, PAS PAR `currentOrganization`.
        // LE CONTRAT D'ORGANISATION VIT DANS UNE SEULE CLASSE, LUE PAR LES DEUX RÉPONSES.
        $payload = array_merge($payload, app(ContratDeRoleMobile::class)->pour($user));

        // LE RÔLE CANONIQUE — ce qui subsume tous les drapeaux ci-dessus.
        $payload['role'] = $user->roleCanonique()->value;
        $payload['is_super_admin'] = $user->roleCanonique() === Role::SUPER_ADMIN;

        // La même clé qu'à la connexion, sous la même forme.
        $payload['email_verified'] = $user->hasVerifiedEmail();

        $payload['user'] = $payload;

        return response()->json($payload);
    }
}
