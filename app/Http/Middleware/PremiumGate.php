<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PremiumGate
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $tier = $user->plan_type ?? 'free';
        $tiers = config('premium.tiers', []);
        $tierConfig = $tiers[$tier] ?? $tiers['free'] ?? [];
        $features = $tierConfig['features'] ?? [];

        if (isset($features[$feature]) && ! $features[$feature]) {
            return response()->json([
                'error' => 'premium_required',
                'message' => 'This feature requires a Pro or Business plan.',
                'upgrade_url' => config('app.url').'/pricing',
            ], 403);
        }

        return $next($request);
    }
}
