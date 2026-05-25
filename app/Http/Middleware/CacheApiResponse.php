<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheApiResponse
{
    public function handle(Request $request, Closure $next, int $ttl = 60): mixed
    {
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $key    = 'api_cache:' . md5($request->fullUrl() . '|' . ($request->user()?->id ?? 'anon'));
        $cached = Cache::get($key);

        if ($cached) {
            return response()->json($cached['data'], $cached['status'])
                ->header('X-Cache', 'HIT');
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($key, [
                'data'   => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], $ttl);
        }

        return $response->header('X-Cache', 'MISS');
    }
}
