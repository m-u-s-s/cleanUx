<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function toResponse($request)
    {
        $user = $request->user();

        // Phase 14.1 — Si provider en cours d'onboarding, rediriger là
        if (
            $user->providerProfile
            && $user->providerProfile->verification_status !== 'verified'
            && $user->providerProfile->onboarding_step < 6
        ) {
            return redirect()->route('provider.onboarding');
        }

        return redirect()->intended(config('fortify.home'));
    }
    
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                return match ($user->role) {
                    'admin' => redirect()->route('admin.dashboard'),
                    'employe' => redirect()->route('employe.dashboard'),
                    'client' => redirect()->route('client.dashboard'),
                    default => redirect('/dashboard'),
                };
            }
        }

        return $next($request);
    }
}
