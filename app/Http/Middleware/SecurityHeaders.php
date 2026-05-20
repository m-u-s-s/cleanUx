<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de sécurité standards (HSTS, X-Frame-Options, CSP, etc.).
 * Configurable via env() pour ajuster CSP en prod si besoin.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Pas de header sur les réponses non-HTML pures (streaming etc.)
        if (! $response instanceof Response) {
            return $response;
        }

        $isProduction = app()->environment('production');

        // HSTS — uniquement en prod et derrière HTTPS
        if ($isProduction && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'));
        $response->headers->set('Referrer-Policy', env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'));
        $response->headers->set('Permissions-Policy', env(
            'SECURITY_PERMISSIONS_POLICY',
            'geolocation=(self), camera=(self), microphone=()'
        ));

        // CSP (loose default — restreindre en prod via env)
        if ($csp = env('SECURITY_CSP')) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Empêche cache des réponses sensibles si auth
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        return $response;
    }
}
