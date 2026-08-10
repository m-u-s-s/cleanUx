<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * EMBARQUÉ DANS UNE WEBVIEW — et le rester en naviguant.
 *
 * Le mode retire la barre de navigation web et la barre d'onglets du bas : dans une WebView, elles
 * DOUBLENT l'en-tête et les onglets natifs qui les entourent déjà.
 *
 * IL ÉTAIT LU SUR LA SEULE REQUÊTE COURANTE, et c'est là qu'il cassait. `?embed=1` n'existe que sur
 * la page d'entrée ; dès le premier lien interne — `route('order.confirmation')`, généré sans
 * paramètre, comme tous les liens de l'application — le drapeau retombait à faux et les deux
 * chromes web réapparaissaient AU MILIEU de la page. Sur le récapitulatif de commande, la barre du
 * bas se dépliait en une colonne d'icônes qui poussait le bouton « Confirmer » hors de l'écran :
 * un client pouvait tout remplir et ne jamais pouvoir valider.
 *
 * LA SESSION EST LE BON PORTEUR. Une WebView a son propre bocal à témoins, distinct du navigateur
 * de l'utilisateur : y coller le drapeau ne peut pas déborder sur une session de bureau, qui ne
 * passe jamais par `webview.enter`. `?embed=0` reste une porte de sortie explicite — utile pour
 * ouvrir une page dans le navigateur du système depuis l'application.
 */
class EmbedMode
{
    public const SESSION_KEY = 'ui.embedded';

    public function handle(Request $request, Closure $next): Response
    {
        $embedded = $this->resolve($request);

        View::share('embedded', $embedded);

        return $next($request);
    }

    private function resolve(Request $request): bool
    {
        // Une sortie explicite prime sur tout le reste, et efface la mémoire de session : sans
        // cela, un onglet resterait embarqué jusqu'à expiration sans moyen d'en sortir.
        if ($request->has('embed') && ! $request->boolean('embed')) {
            $request->session()->forget(self::SESSION_KEY);

            return false;
        }

        if ($request->boolean('embed') || $request->header('X-Embedded') === '1') {
            $request->session()->put(self::SESSION_KEY, true);

            return true;
        }

        return (bool) $request->session()->get(self::SESSION_KEY, false);
    }
}
