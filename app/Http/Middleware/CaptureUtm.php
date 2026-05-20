<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture UTM params (utm_source, utm_medium, utm_campaign, etc.) en session
 * pour attribution. Persisté 90 jours via cookie. À brancher au login pour
 * tag le User avec sa source d'acquisition.
 */
class CaptureUtm
{
    protected const UTM_KEYS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $utm = [];
        foreach (self::UTM_KEYS as $key) {
            if ($request->query($key)) {
                $utm[$key] = mb_substr((string) $request->query($key), 0, 255);
            }
        }

        if (! empty($utm)) {
            $utm['_captured_at'] = now()->toIso8601String();
            $utm['_referrer'] = mb_substr((string) $request->header('referer', ''), 0, 500);

            // Persist en session
            $request->session()->put('utm_attribution', $utm);

            // Et en cookie pour 90j (cross-session)
            cookie()->queue('cleanux_utm', json_encode($utm), 60 * 24 * 90);
        }

        return $next($request);
    }
}
