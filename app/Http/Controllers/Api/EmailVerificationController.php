<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/auth/email/verification-notification Renvoie l'e-mail de vérification.
 *
 * Les trois routes de Fortify sont gardées par `auth:web` : l'application, qui ne porte qu'un
 * jeton, savait dire « adresse non vérifiée » sans pouvoir rien y faire.
 *
 * @group Authentication
 *
 * @authenticated
 */
class EmailVerificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Idempotent : redemander pour une adresse déjà confirmée n'est pas une erreur.
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'ok' => true,
                'already_verified' => true,
                'message' => __('Votre adresse e-mail est déjà confirmée.'),
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'ok' => true,
            'already_verified' => false,
            'message' => __('Un nouvel e-mail de confirmation vient de vous être envoyé.'),
        ]);
    }
}
