<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Vérifie un challenge Cloudflare Turnstile pour bloquer les bots. */
class VerifyTurnstileCaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        // La clé `services.turnstile.secret_key` existe dans config/services.php :
        // le repli env() qui traînait ici n'était jamais atteint, et aurait de toute
        // façon rendu null sous `config:cache`.
        $secretKey = (string) config('services.turnstile.secret_key');

        // Soft-bypass en dev/test si pas configuré
        if ($secretKey === '') {
            if (app()->environment('production')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'captcha_misconfigured',
                    'message' => 'Captcha non configuré côté serveur.',
                ], 503);
            }

            return $next($request);
        }

        $token = (string) $request->input('cf-turnstile-response',
            $request->header('X-Turnstile-Token', ''));

        if ($token === '') {
            return response()->json([
                'ok' => false,
                'error' => 'captcha_required',
                'message' => 'Validation captcha requise.',
            ], 400);
        }

        try {
            $response = Http::timeout(5)->asForm()->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]
            );

            $result = $response->json();
            if (! is_array($result) || ! (bool) ($result['success'] ?? false)) {
                $errorCodes = $result['error-codes'] ?? [];

                return response()->json([
                    'ok' => false,
                    'error' => 'captcha_invalid',
                    'message' => 'Validation captcha échouée.',
                    'codes' => $errorCodes,
                ], 403);
            }
        } catch (\Throwable $e) {
            // Si l'API Cloudflare est down, on log + bloque (fail-closed prod, fail-open dev)
            Log::warning('[turnstile] verification network failure', [
                'error' => $e->getMessage(),
            ]);
            if (app()->environment('production')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'captcha_service_unavailable',
                ], 503);
            }
            // Dev : fail-open pour ne pas bloquer le dev local sans Internet
        }

        return $next($request);
    }
}
