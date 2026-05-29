<?php

namespace App\Http\Middleware;

use App\Services\Analytics\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

            // Persist en session (request-scoped, perdu au logout)
            if ($request->hasSession()) {
                $request->session()->put('utm_attribution', $utm);
            }

            // Et en cookie pour 90j (cross-session) — HttpOnly false (lecture JS analytics OK)
            cookie()->queue(cookie('cleanux_utm', json_encode($utm), 60 * 24 * 90, '/', null, false, false));

            // Track analytics event si module présent (soft-fail)
            $this->trackAttributionEvent($request, $utm);

            // Persiste sur le User si authentifié (premier touchpoint = source d'acquisition)
            $this->persistOnUser($request, $utm);
        }

        return $next($request);
    }

    /**
     * Track event AnalyticsEvent type="acquisition.utm_captured" pour funnel attribution.
     */
    protected function trackAttributionEvent(Request $request, array $utm): void
    {
        try {
            if (! class_exists(AnalyticsService::class)) {
                return;
            }
            if (! Schema::hasTable('analytics_events')) {
                return;
            }
            app(AnalyticsService::class)->track(
                'acquisition.utm_captured',
                array_merge($utm, ['url' => mb_substr((string) $request->fullUrl(), 0, 500)]),
                [
                    'category' => 'acquisition',
                    'idempotency_key' => null,
                ],
            );
        } catch (\Throwable $e) {
            // soft-fail — UTM tracking ne doit JAMAIS bloquer la requête
        }
    }

    /**
     * Si l'utilisateur est authentifié, store sur le User pour attribution lifetime.
     * Idempotent : on ne ré-écrit que si pas déjà set (first-touch attribution).
     */
    protected function persistOnUser(Request $request, array $utm): void
    {
        try {
            $user = $request->user();
            if (! $user || ! Schema::hasColumn('users', 'acquisition_metadata')) {
                return;
            }
            $existing = (array) ($user->acquisition_metadata ?? []);
            if (! empty($existing['utm_source'] ?? null)) {
                return;   // first-touch déjà capturé
            }
            $user->forceFill([
                'acquisition_metadata' => $utm,
            ])->save();
        } catch (\Throwable) {
            // soft-fail
        }
    }
}
