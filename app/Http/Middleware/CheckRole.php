<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        foreach ($roles as $role) {
            if (method_exists($user, 'matchesRole') && $user->matchesRole($role)) {
                return $next($request);
            }

            if ($user->role === $role) {
                return $next($request);
            }
        }

        abort(403);
    }
}
