<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $status = strtolower((string) ($user->status ?? 'active'));

        if (! $user->is_active || in_array($status, ['inactive', 'disabled', 'suspended', 'blocked'], true)) {
            auth()->logout();

            abort(403, 'Compte inactif ou suspendu.');
        }

        return $next($request);
    }
}
