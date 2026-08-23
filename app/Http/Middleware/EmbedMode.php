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
