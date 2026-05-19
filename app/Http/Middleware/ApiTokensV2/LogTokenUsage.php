<?php

namespace App\Http\Middleware\ApiTokensV2;

use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Services\ApiTokensV2\UsageLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log usage row + update last_used_at/usage_count after response.
 * Bypass si pas de token Sanctum (session web).
 */
class LogTokenUsage
{
    public function __construct(protected UsageLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        $user = $request->user();
        $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if ($token instanceof PersonalAccessTokenV2 && $this->logger->shouldLog($request)) {
            $this->logger->record($request, $response, $token, $latencyMs);
        }
        return $response;
    }
}
