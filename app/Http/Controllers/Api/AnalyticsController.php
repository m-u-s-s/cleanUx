<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Analytics ingestion API — endpoints client SDK (web/mobile).
 *
 *   - POST /api/analytics/track     → track 1 event
 *   - POST /api/analytics/page      → track page view
 *   - POST /api/analytics/identify  → link anonymous_id → user_id
 *
 * Auth optionnelle (auth:sanctum?) : si non authentifié, le tracking
 * fonctionne quand même en mode anonyme via `anonymous_id` + cookie session.
 * Validation stricte du nom d'event (whitelist config).
 */
class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics)
    {
    }

    public function track(Request $request): JsonResponse
    {
        if (! $this->passesRateLimit($request)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $data = $request->validate([
            'event' => ['required', 'string', 'max:128'],
            'properties' => ['nullable', 'array'],
            'anonymous_id' => ['nullable', 'string', 'max:64'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'url' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'platform' => ['nullable', 'string', 'max:32'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $event = $this->analytics->track(
            $data['event'],
            (array) ($data['properties'] ?? []),
            [
                'user' => $request->user(),
                'request' => $request,
                'anonymous_id' => $data['anonymous_id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'url' => $data['url'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                'platform' => $data['platform'] ?? null,
                'occurred_at' => isset($data['occurred_at']) ? \Carbon\Carbon::parse($data['occurred_at']) : null,
            ],
        );

        if (! $event) {
            return response()->json(['ok' => true, 'tracked' => false]);
        }

        return response()->json([
            'ok' => true,
            'tracked' => true,
            'event_id' => $event->id,
            'session_id' => $event->session_id,
        ], 201);
    }

    public function page(Request $request): JsonResponse
    {
        $request->merge(['event' => 'page.viewed']);
        return $this->track($request);
    }

    public function identify(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        $data = $request->validate([
            'anonymous_id' => ['required', 'string', 'max:64'],
        ]);

        $count = $this->analytics->identify($data['anonymous_id'], $user);

        return response()->json([
            'ok' => true,
            'linked_count' => $count,
        ]);
    }

    protected function passesRateLimit(Request $request): bool
    {
        $perIp = (int) config('analytics.rate_limits.per_ip_per_minute', 240);
        $perUser = (int) config('analytics.rate_limits.per_user_per_minute', 300);

        $ipKey = 'analytics:ip:' . sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($ipKey, $perIp)) {
            return false;
        }
        RateLimiter::hit($ipKey, 60);

        if ($user = $request->user()) {
            $userKey = 'analytics:user:' . $user->id;
            if (RateLimiter::tooManyAttempts($userKey, $perUser)) {
                return false;
            }
            RateLimiter::hit($userKey, 60);
        }

        return true;
    }
}
