<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de sécurité standards (HSTS, X-Frame-Options, CSP, etc.).
 *
 * Les valeurs se règlent dans config/security.php, jamais par env() directement :
 * env() rend null dès que `config:cache` a tourné, donc en production, ce qui
 * aurait vidé ces en-têtes en silence.
 */
class SecurityHeaders
{
    /**
     * La CSP servie en production quand rien n'est configuré.
     *
     * ELLE VIT ICI, ET NON DANS LA CONFIG, PARCE QU'ELLE EST UN PLANCHER. `config/security.php`
     * la reprend comme valeur par défaut, si bien qu'il n'existe qu'une seule chaîne à maintenir :
     * un fichier de config absent ou une clé mal orthographiée ne peut plus faire disparaître la
     * CSP de production sans un mot. Recopier la chaîne à deux endroits aurait produit, tôt ou
     * tard, deux CSP différentes selon le chemin emprunté.
     */
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

        /*
         * LE PLANCHER EST DANS LE CODE, PAS SEULEMENT DANS LA CONFIG.
         *
         * `(string) null` vaut '' : sans ce repli, une clé de config absente — fichier non publié,
         * faute de frappe, cache partiel — ferait poser un en-tête VIDE. Un en-tête vide ne protège
         * de rien et ne se distingue pas d'un en-tête correct dans un journal : la protection
         * disparaîtrait en silence, ce qui est précisément le défaut que le passage de env() vers
         * la config était censé fermer.
         *
         * Ces valeurs sont les mêmes que les défauts de config/security.php. La duplication est
         * assumée : c'est une ceinture et des bretelles sur un chemin qui traverse TOUTES les
         * requêtes.
         */
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
