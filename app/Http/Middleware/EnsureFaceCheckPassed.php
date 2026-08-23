<?php

namespace App\Http\Middleware;

use App\Services\FaceCheck\FaceCheckGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Routeur;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** La porte du contrôle facial sur toute une surface de routes. */
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

    /** L'IDENTITÉ D'APPAREIL DONT ON DISPOSE VRAIMENT. */
    private function appareil(Request $request): ?string
    {
        $jeton = $request->user()?->currentAccessToken();

        // `currentAccessToken()` NE REND PAS TOUJOURS UN JETON.
        if (! $jeton instanceof PersonalAccessToken) {
            return null;
        }

        return filled($jeton->name) ? (string) $jeton->name : null;
    }
}
