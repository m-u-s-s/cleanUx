<?php

namespace App\Http\Middleware;

use App\Http\Middleware\ApiTokensV2\EnforceTokenScope;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde d'administration du groupe d'API `/api/admin/*`.
 *
 * CE QU'ELLE CORRIGE. Le groupe n'avait pour toute protection que `api_scope`. Or le jeton mobile
 * est créé par `createToken()` sans liste d'abilities : Sanctum y inscrit '*', et
 * {@see EnforceTokenScope} laisse passer tout jeton portant '*'.
 * Un contrôle de scope dit ce qu'un jeton a le droit de faire ; il ne dit pas QUI le porte. La
 * garde de rôle était le verrou manquant : un client connecté depuis l'application mobile
 * atteignait la comptabilité, les jetons d'API, la modération et la flotte.
 *
 * POURQUOI UN MIDDLEWARE DÉDIÉ PLUTÔT QUE `role:admin`. Le groupe d'API répond en JSON de forme
 * `{ok, error}` ; `CheckRole` appelle `abort(403)` et confie le rendu au gestionnaire d'exceptions,
 * dont la forme dépend des en-têtes négociés. Une console mobile doit pouvoir distinguer « pas
 * administrateur » de « session expirée » sur un code stable, pas sur une page d'erreur négociée.
 *
 * POURQUOI 401 ET 403 SONT DISTINGUÉS. L'application efface son jeton sur 401 et affiche un refus
 * sur 403 : confondre les deux ferait soit boucler des reconnexions, soit garder un jeton mort.
 */
class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // `instanceof` plutôt qu'un simple test de nullité : un authentifiable d'un autre garde
        // n'a pas de notion d'administrateur, et le traiter comme non authentifié vaut mieux que
        // de lui poser une question à laquelle il ne peut pas répondre.
        if (! $user instanceof User) {
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        if (! $user->isAdmin()) {
            return response()->json(['ok' => false, 'error' => 'forbidden_not_admin'], 403);
        }

        return $next($request);
    }
}
