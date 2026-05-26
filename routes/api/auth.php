<?php

use App\Http\Controllers\Api\Auth\ApiAuthController;
use App\Http\Controllers\Api\AuthMeController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public — Auth (login / register / forgot-password)
// ─────────────────────────────────────────────

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('/login',    [ApiAuthController::class, 'login']);
    Route::post('/register', [ApiAuthController::class, 'register'])->middleware('turnstile');
});

// POST /auth/forgot-password — silently ignores unknown emails (mobile app)
Route::post('/auth/forgot-password', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => 'required|email']);
    try {
        \Illuminate\Support\Facades\Password::sendResetLink($request->only('email'));
    } catch (\Throwable $e) {
        // silently ignore — don't reveal if email exists
    }
    return response()->json(['ok' => true, 'message' => 'If this email exists, a reset link has been sent.']);
})->middleware('throttle:5,1');

// ─────────────────────────────────────────────
// Authenticated — Token management + identity
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Token refresh + grace period + identity
    Route::middleware('token.grace')->group(function () {
        Route::post('/auth/refresh', \App\Http\Controllers\Api\AuthRefreshController::class)
            ->name('api.auth.refresh');
        Route::get('/auth/me', AuthMeController::class)->name('api.auth.me');
    });

    Route::post('/auth/logout',     [ApiAuthController::class, 'logout']);
    Route::post('/auth/logout-all', [ApiAuthController::class, 'logoutAll']);
});
