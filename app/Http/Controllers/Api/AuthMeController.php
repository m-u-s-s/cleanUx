<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Support\Mobile\AppAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 *
 * @authenticated
 *
 * GET /api/auth/me
 *
 * Returns the currently authenticated user. Extracted from a closure so
 * that this route is compatible with `php artisan route:cache`.
 */
class AuthMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
         * La reprise de session est le SECOND verrou de la séparation des deux applications.
         *
         * Bloquer la connexion ne suffit pas : un jeton obtenu avant ce garde-fou, ou dans l'autre
         * APK, resterait valide indéfiniment. Le 403 ici fait tomber le `catch` de l'application,
         * qui efface le jeton — la session se referme d'elle-même.
         */
        $app = AppAudience::declared($request);

        if (! AppAudience::allows($user, $app)) {
            return response()->json(AppAudience::refusal($user, (string) $app) + ['ok' => false], 403);
        }

        // SP2 — expose le flag premium pour piloter la sélection prestataire côté
        // mobile (parité web). Sérialisé par-dessus l'utilisateur sans le muter.
        $payload = $user->toArray();
        $profile = $user->customerProfile;
        $payload['is_premium'] = $profile instanceof CustomerProfile && $profile->isPremium();

        /*
         * La reprise de session doit dire la même chose que la connexion.
         *
         * `login` sérialise explicitement `is_admin` ; ici la réponse était bâtie sur
         * `$user->toArray()`, qui ne porte que des colonnes. L'administrateur redevenait donc un
         * compte ordinaire à chaque redémarrage de l'application mobile — avec un jeton pourtant
         * valide — et l'aiguillage d'espace l'envoyait là où rien ne lui répond.
         *
         * Ce drapeau est un AIGUILLAGE D'INTERFACE, pas une frontière de privilèges : celle-ci
         * reste tenue par les gardes de rôle sur chaque route.
         */
        $payload['is_admin'] = method_exists($user, 'isAdmin') && $user->isAdmin();

        /*
         * La casquette prestataire, pour la même raison — et pour une de plus.
         *
         * Un compte peut porter les DEUX : un administrateur qui intervient aussi sur le terrain
         * existe. Sans ce drapeau à la reprise de session, l'application ne pouvait plus lui
         * proposer de choisir son espace et l'enfermait du côté administration, sans retour.
         */
        $payload['is_provider'] = method_exists($user, 'isProvider') && $user->isProvider();

        /*
         * La réponse porte les DEUX formes, et c'est délibéré.
         *
         * Le serveur renvoyait les attributs à plat ; l'application mobile lit `data.user`. Elle
         * recevait donc `undefined` à chaque reprise de session, en concluait qu'il n'y avait
         * personne, et renvoyait vers l'écran de connexion — un jeton valide en poche, une
         * reconnexion à chaque lancement. Rien ne le signalait : le test mobile simulait
         * `{ user: … }`, une forme que le serveur n'a jamais envoyée.
         *
         * Retirer la forme à plat casserait les consommateurs qui la lisent déjà — deux tests la
         * figent. On ajoute donc, on ne remplace pas.
         */
        $payload['user'] = $payload;

        return response()->json($payload);
    }
}
