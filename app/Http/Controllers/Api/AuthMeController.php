<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
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
        /*
         * LA CASQUETTE SOCIÉTÉ, POUR LA MÊME RAISON QUE `is_admin`.
         *
         * `ApiAuthController::serializeUser()` expose déjà `is_entreprise` et
         * `organization_account_id` à la CONNEXION. Ici la réponse était bâtie sur
         * `$user->toArray()`, qui ne porte que des colonnes : un compte société redevenait un
         * particulier à chaque reprise de session, jeton valide en poche.
         *
         * `organization_type` s'y ajoute parce que le drapeau booléen ne suffit pas : une société
         * CLIENTE et une société PRESTATAIRE ouvrent deux espaces différents, et l'application ne
         * peut pas les distinguer sans lui. Il vaut `null` pour un particulier — l'absence
         * d'organisation est une information, pas un cas par défaut.
         */
        $payload['is_entreprise'] = method_exists($user, 'isEntreprise') && $user->isEntreprise();
        $payload['organization_account_id'] = $user->organization_account_id
            ?? $user->current_organization_id
            ?? null;

        /*
         * `organization_accounts.type` n'est PAS casté en enum sur le modèle : c'est une chaîne.
         * J'avais écrit une normalisation `instanceof` par prudence — PHPStan a montré qu'elle ne
         * s'exécutait jamais. Une garde morte donne l'illusion d'une protection ; on la retire.
         */
        $payload['organization_type'] = $user->currentOrganization?->type;

        /*
         * LE RÔLE CANONIQUE — ce qui subsume tous les drapeaux ci-dessus.
         *
         * Chacun d'eux a été ajouté APRÈS avoir manqué : `is_admin`, puis `is_provider`, puis
         * `is_entreprise`, puis `organization_type`. L'application devait ensuite les recombiner
         * elle-même, avec sa propre idée de l'ordre — c'est ainsi qu'une conjonction impossible a
         * rendu cinq écrans société inatteignables pendant toute une livraison.
         *
         * `role` ÉCRASE ICI LA COLONNE HÉRITÉE du même nom, que `toArray()` sérialisait. Cette
         * colonne dit `client` / `employe` / `entreprise` / `admin` — précisément la nomenclature
         * remplacée. Elle n'est lue nulle part dans les applications mobiles (`role: string` est
         * déclaré dans leurs types et jamais consommé), et le web lit le modèle, pas cette
         * réponse.
         *
         * Les drapeaux restent : les retirer casserait les applications déjà installées.
         */
        $payload['role'] = $user->roleCanonique()->value;
        $payload['is_super_admin'] = $user->roleCanonique() === Role::SUPER_ADMIN;

        $payload['user'] = $payload;

        return response()->json($payload);
    }
}
