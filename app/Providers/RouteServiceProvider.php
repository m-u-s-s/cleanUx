<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // SMS-cost endpoints : 5/min per IP, 20/h per user
        RateLimiter::for('otp', function (Request $request) {
            return [
                Limit::perMinute(5)->by('otp:ip:' . $request->ip()),
                Limit::perHour(20)->by('otp:user:' . ($request->user()?->id ?: $request->ip())),
            ];
        });

        // Auth endpoints (login/register/password-reset) : 10/min per IP brute-force guard
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Promo/referral redeem : 10/min per user pour limiter le code abuse
        RateLimiter::for('promo', function (Request $request) {
            return Limit::perMinute(10)->by('promo:' . ($request->user()?->id ?: $request->ip()));
        });

        // Chat message send : 30/min per user (anti-spam)
        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(30)->by('chat:' . ($request->user()?->id ?: $request->ip()));
        });

        // External provider calls (KYB sanctions, geocoding, distance matrix) : 20/min per user
        RateLimiter::for('external', function (Request $request) {
            return Limit::perMinute(20)->by('ext:' . ($request->user()?->id ?: $request->ip()));
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
