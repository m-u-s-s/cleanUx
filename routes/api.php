<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ApiNotificationController;
use App\Http\Controllers\Api\ModulesController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public routes — health, FAQ, countries, locales,
// search, analytics, FX, pricing, GDPR download
// ─────────────────────────────────────────────
require __DIR__.'/api/public.php';

// ─────────────────────────────────────────────
// Auth routes — login, register, forgot-password,
// logout, refresh, me, token grace
// ─────────────────────────────────────────────
require __DIR__.'/api/auth.php';

// ─────────────────────────────────────────────
// Realtime — socket config + broadcasting auth
// ─────────────────────────────────────────────
require __DIR__.'/api/realtime.php';

// ─────────────────────────────────────────────
// Shared authenticated routes
// ─────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    /*
     * Le catalogue des modules du rôle. Le contexte se déduit du jeton, il n'est pas un paramètre :
     * voir `ModulesController`.
     */
    Route::get('/modules', [ModulesController::class, 'index'])
        ->name('api.modules.index');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    // Notifications
    Route::get('/notifications', [ApiNotificationController::class, 'index']);
    // `read-all` AVANT `{id}` : sinon la chaîne « read-all » serait prise pour un identifiant.
    Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/{id}', [ApiNotificationController::class, 'show']);
    Route::post('/notifications/{id}/read', [ApiNotificationController::class, 'markAsRead']);

    // Quick user shortcut (Laravel default)
    Route::get('/user', fn (Request $request) => $request->user());

    // Theme preference
    Route::post('/user/theme', function (Request $request) {
        $request->validate(['theme' => ['required', 'in:light,dark,system']]);
        $request->user()->update(['theme_preference' => $request->theme]);

        return response()->json(['ok' => true]);
    });

    // Phase Analytics v2 — Identify (link anonymous_id → user_id)
    Route::post('/analytics/identify', [AnalyticsController::class, 'identify']);
});

// ─────────────────────────────────────────────
// Client endpoints (prefix: /client)
// ─────────────────────────────────────────────
require __DIR__.'/api/client.php';

// ─────────────────────────────────────────────
// Provider endpoints
// ─────────────────────────────────────────────
require __DIR__.'/api/provider.php';

// ─────────────────────────────────────────────
// Admin endpoints
// ─────────────────────────────────────────────
require __DIR__.'/api/admin.php';

// ─────────────────────────────────────────────
// v2 shared routes (onboarding, contracts, fleet,
// KYB, tenancy, subscriptions, chat, API tokens,
// geo, cancellation v2 client/provider)
// ─────────────────────────────────────────────
require __DIR__.'/api/v2-shared.php';
