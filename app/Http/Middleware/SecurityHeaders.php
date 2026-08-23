<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Headers de sécurité standards (HSTS, X-Frame-Options, CSP, etc.). */
class SecurityHeaders
{
    /** La CSP servie en production quand rien n'est configuré. */
    public const CSP_PRODUCTION_PAR_DEFAUT = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' wss: https:; frame-src 'self' https://js.stripe.com https://challenges.cloudflare.com; object-src 'none'; base-uri 'self';";

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

        // LE PLANCHER EST DANS LE CODE, PAS SEULEMENT DANS LA CONFIG.
        $response->headers->set(
            'X-Frame-Options',
            (string) (config('security.x_frame_options') ?: 'SAMEORIGIN'),
        );
        $response->headers->set(
            'Referrer-Policy',
            (string) (config('security.referrer_policy') ?: 'strict-origin-when-cross-origin'),
        );
        $response->headers->set(
            'Permissions-Policy',
            (string) (config('security.permissions_policy') ?: 'geolocation=(self), camera=(self), microphone=()'),
        );

        // CSP — le repli strict ne s'applique QU'EN production, et seulement si
        // aucune CSP explicite n'est configurée. Hors production, pas de CSP du
        // tout : Vite et les inspecteurs ont besoin de respirer.
        $csp = config('security.csp');
        if (! $csp && $isProduction) {
            // Même plancher que les en-têtes ci-dessus : une clé de config absente ne doit pas
            // faire disparaître la CSP de production sans un mot.
            $csp = config('security.csp_production_fallback') ?: self::CSP_PRODUCTION_PAR_DEFAUT;
        }
        if ($csp) {
            $response->headers->set('Content-Security-Policy', (string) $csp);
        }

        // Empêche cache des réponses sensibles si auth
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        return $response;
    }
}
