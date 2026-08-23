<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Garde d'administration du groupe d'API `/api/admin/*`. CE QU'ELLE CORRIGE. */
class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // `instanceof` plutôt qu'un simple test de nullité : un authentifiable d'un autre garde
        // n'a pas de notion d'administrateur, et le traiter comme non authentifié vaut mieux que
        // de lui poser une question à laquelle il ne peut pas répondre.
        // Les DEUX conventions de code d'erreur de la plateforme.
        if (! $user instanceof User) {
            return response()->json([
                'ok' => false,
                'error' => 'unauthenticated',
                'error_code' => 'unauthenticated',
            ], 401);
        }

        if (! $user->isAdmin()) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden_not_admin',
                'error_code' => 'forbidden_not_admin',
            ], 403);
        }

        return $next($request);
    }
}
