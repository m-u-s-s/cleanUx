<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Referme la porte sur un compte suspendu, désactivé ou bloqué.
 *
 * La condition elle-même vit dans `User::compteActif()` — voir le commentaire qui l'accompagne :
 * elle est lue ici, à l'authentification par jeton Sanctum et à la connexion mobile.
 *
 * DEUX DÉTAILS QUI ONT LEUR RAISON :
 *
 * 1. `auth()->logout()` n'est appelé QUE si une session existe. Sur une requête d'API il n'y a pas
 *    de session (le groupe `api` ne démarre pas `StartSession`) et le garde de session lèverait
 *    « Session store not set on request » — un 500 à la place d'un 403, sur le chemin même qui
 *    refuse un compte banni.
 * 2. Le refus prend la forme attendue par l'appelant : JSON pour l'application mobile, page 403
 *    pour le navigateur. Une page HTML rendue dans un client mobile ne s'affiche nulle part.
 */
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
