<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Referme la porte sur un compte suspendu, désactivé ou bloqué. */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->refus($request, 403, 'Accès refusé.');
        }

        if (! $user->compteActif()) {
            if ($request->hasSession()) {
                auth()->logout();
            }

            return $this->refus($request, 403, 'Compte inactif ou suspendu.');
        }

        return $next($request);
    }

    private function refus(Request $request, int $statut, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'error_code' => 'compte_inactif',
                'message' => $message,
            ], $statut);
        }

        abort($statut, $message);
    }
}
