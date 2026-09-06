<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** EMBARQUÉ DANS UNE WEBVIEW — et le rester en naviguant. */
class EmbedMode
{
    public const SESSION_KEY = 'ui.embedded';

    /**
     * LE MEME FAIT, HORS SESSION.
     *
     * `ui.embedded` disparait avec la session qui l'a pose — et c'est PRECISEMENT a ce
     * moment-la qu'il faut savoir qu'on parle a une WebView, pour lui rendre la page que
     * son pont attend au lieu d'un formulaire de connexion qu'elle ne sait pas remplir.
     */
    public const COOKIE = 'brio_embed';

    public function handle(Request $request, Closure $next): Response
    {
        $embedded = $this->resolve($request);

        View::share('embedded', $embedded);

        $response = $next($request);

        if ($embedded && $request->cookie(self::COOKIE) !== '1') {
            $response->headers->setCookie(cookie(self::COOKIE, '1', 60 * 24 * 30, null, null, null, true));
        } elseif (! $embedded && $request->cookie(self::COOKIE) !== null) {
            $response->headers->setCookie(cookie()->forget(self::COOKIE));
        }

        return $response;
    }

    /** Embarque, meme sans session : le cookie, l'URL ou l'en-tete suffisent. */
    public static function estEmbarque(Request $request): bool
    {
        if ($request->has('embed') && ! $request->boolean('embed')) {
            return false;
        }

        return $request->cookie(self::COOKIE) === '1'
            || $request->boolean('embed')
            || $request->header('X-Embedded') === '1'
            || ($request->hasSession() && (bool) $request->session()->get(self::SESSION_KEY, false));
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
