<?php

namespace App\Http\Middleware;

use App\Services\FaceCheck\FaceCheckGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Routeur;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * La porte du contrôle facial sur toute une surface de routes.
 *
 * Écrite sur le patron exact d'`EnsureProviderIsApproved` : réponse JSON `ok:false` + `error_code`
 * pour le mobile, redirection vers le parcours de remédiation pour le web, et exemptions explicites
 * par `withoutMiddleware('face.verified')`.
 *
 * ELLE NE SUFFIT PAS À ELLE SEULE, et c'est important de le savoir : elle ne couvre ni le web
 * `routes/employe.php`, ni `api/provider/company/*`, ni les chemins internes qui ne passent par
 * aucune route prestataire — l'affectation d'un salarié par sa société, par exemple. Les gardes de
 * service posées dans `DispatchEngine`, `MissionDispatchService`, `MissionLifecycleService`,
 * `MissionAssignmentService` et les deux services de présence sont là pour ça. Un middleware seul
 * donne l'illusion d'une couverture complète.
 */
class EnsureFaceCheckPassed
{
    public function __construct(
        private readonly FaceCheckGate $gate,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $verdict = $this->gate->inspectProvider($user, $this->appareil($request));

        if ($verdict->allowed()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json($verdict->toPayload(), 403);
        }

        $cible = Routeur::has('provider.face-check') ? 'provider.face-check' : 'home';

        return redirect()->route($cible)->with('warning', $verdict->message);
    }

    /**
     * L'IDENTITÉ D'APPAREIL DONT ON DISPOSE VRAIMENT.
     *
     * Il n'existe aucune table d'appareils liée à l'authentification sur ce dépôt : le seul
     * marqueur est le nom du jeton Sanctum, posé à la connexion depuis `device_name`. C'est
     * imparfait — deux téléphones peuvent porter le même nom — mais c'est ce qui distingue un
     * jeton d'un autre, et c'est suffisant pour repérer un compte qui se met soudain à travailler
     * depuis ailleurs. Une session web n'en porte pas : elle ne déclenche donc rien.
     */
    private function appareil(Request $request): ?string
    {
        $jeton = $request->user()?->currentAccessToken();

        /*
         * `currentAccessToken()` NE REND PAS TOUJOURS UN JETON.
         *
         * Sur une requete authentifiee par la SESSION -- le web, et `Sanctum::actingAs()` dans les
         * tests -- Sanctum rend un `TransientToken` : un objet sans table, sans colonnes, et donc
         * SANS `name`. Lire `->name` dessus leve « Undefined property » et fait tomber la requete
         * entiere. Mesure : quatorze tests de mission et de presence sont tombes d'un coup, tous
         * pour cette seule ligne.
         *
         * Une session n'a pas d'identite d'appareil, et c'est tres bien : elle ne declenche alors
         * aucun controle hors cadence, ce qui est exactement le comportement voulu.
         */
        if (! $jeton instanceof PersonalAccessToken) {
            return null;
        }

        return filled($jeton->name) ? (string) $jeton->name : null;
    }
}
