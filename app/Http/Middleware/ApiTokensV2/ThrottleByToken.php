<?php

namespace App\Http\Middleware\ApiTokensV2;

use App\Models\Sanctum\PersonalAccessTokenV2;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting per access token : utilise personal_access_tokens.rate_limit_per_minute
 * (ou default config). Si pas de token (session web), bypass.
 */
class ThrottleByToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if (! $token instanceof PersonalAccessTokenV2) {
            return $next($request);
        }
        $limit = $token->effectiveRateLimit();
        if ($limit <= 0) {
            return $next($request);
        }
        $key = 'api_token:' . $token->id;
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);
            return response()->json([
                'ok' => false,
                'error' => 'rate_limited',
                'retry_after_seconds' => $retryAfter,
                'limit_per_minute' => $limit,
            ], 429)->header('Retry-After', (string) $retryAfter);
        }
        RateLimiter::hit($key, 60);
        return $next($request);
    }
}
