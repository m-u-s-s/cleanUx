<?php

use App\Http\Controllers\Api\ApiNotificationController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public routes — health, FAQ, countries, locales,
// search, analytics, FX, pricing, GDPR download
// ─────────────────────────────────────────────
require __DIR__ . '/api/public.php';

// ─────────────────────────────────────────────
// Auth routes — login, register, forgot-password,
// logout, refresh, me, token grace
// ─────────────────────────────────────────────
require __DIR__ . '/api/auth.php';

// ─────────────────────────────────────────────
// Realtime — socket config + broadcasting auth
// ─────────────────────────────────────────────
require __DIR__ . '/api/realtime.php';

// ─────────────────────────────────────────────
// Shared authenticated routes
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Profile
    Route::get('/profile',   [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    // Notifications
    Route::get('/notifications',             [ApiNotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',  [ApiNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',   [ApiNotificationController::class, 'markAllAsRead']);

    // Quick user shortcut (Laravel default)
    Route::get('/user', fn(Request $request) => $request->user());

    // Phase Analytics v2 — Identify (link anonymous_id → user_id)
    Route::post('/analytics/identify', [\App\Http\Controllers\Api\AnalyticsController::class, 'identify']);
});

// ─────────────────────────────────────────────
// Client endpoints (prefix: /client)
// ─────────────────────────────────────────────
require __DIR__ . '/api/client.php';

// ─────────────────────────────────────────────
// Provider endpoints
// ─────────────────────────────────────────────
require __DIR__ . '/api/provider.php';

// ─────────────────────────────────────────────
// Admin endpoints
// ─────────────────────────────────────────────
require __DIR__ . '/api/admin.php';

// ─────────────────────────────────────────────
// v2 shared routes (onboarding, contracts, fleet,
// KYB, tenancy, subscriptions, chat, API tokens,
// geo, cancellation v2 client/provider)
// ─────────────────────────────────────────────
require __DIR__ . '/api/v2-shared.php';
