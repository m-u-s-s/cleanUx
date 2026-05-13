<?php

use App\Http\Controllers\Api\ApiNotificationController;
use App\Http\Controllers\Api\Auth\ApiAuthController;
use App\Http\Controllers\Api\Client\CancellationController;
use App\Http\Controllers\Api\Client\ClientBookingController;
use App\Http\Controllers\Api\EmployeeMissionTrackingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Provider\ProviderCancellationController;
use App\Http\Controllers\Api\Provider\ProviderMissionLifecycleController;
use App\Http\Controllers\Api\Provider\ProviderOnboardingController;
use App\Http\Controllers\Api\Provider\ProviderPayoutsController;
use App\Http\Controllers\Api\ProviderMissionAssignmentController;
use App\Http\Controllers\Api\ProviderPresenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public — Auth
// ─────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/login',    [ApiAuthController::class, 'login']);
    Route::post('/register', [ApiAuthController::class, 'register']);
});

// ─────────────────────────────────────────────
// Authenticated routes (Sanctum)
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout',     [ApiAuthController::class, 'logout']);
    Route::post('/auth/logout-all', [ApiAuthController::class, 'logoutAll']);

    // Profile
    Route::get('/profile',   [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    // Notifications
    Route::get('/notifications',                  [ApiNotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',       [ApiNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',        [ApiNotificationController::class, 'markAllAsRead']);

    // Quick user shortcut (Laravel default)
    Route::get('/user', fn(Request $request) => $request->user());

    // ─────────────────────────────────────────
    // Client endpoints
    // ─────────────────────────────────────────

    Route::prefix('client')->group(function () {
        Route::get('/bookings',                   [ClientBookingController::class, 'index']);
        Route::post('/bookings',                  [ClientBookingController::class, 'store']);
        Route::get('/bookings/{booking}',         [ClientBookingController::class, 'show']);
        Route::post('/bookings/{booking}/cancel', [ClientBookingController::class, 'cancel']);
        Route::get('/bookings/{booking}/eta',     [ClientBookingController::class, 'eta']);
    });

    // ─────────────────────────────────────────
    // Provider endpoints
    // ─────────────────────────────────────────

    // Phase 0 — Mission tracking (existant)
    Route::post('/missions/{mission}/tracking/start',           [EmployeeMissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/push',    [EmployeeMissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/stop',    [EmployeeMissionTrackingController::class, 'stop']);

    // Phase 11 — Provider presence
    Route::prefix('provider/presence')->group(function () {
        Route::post('/online',    [ProviderPresenceController::class, 'online']);
        Route::post('/offline',   [ProviderPresenceController::class, 'offline']);
        Route::post('/heartbeat', [ProviderPresenceController::class, 'heartbeat']);
        Route::get('/me',         [ProviderPresenceController::class, 'me']);
    });

    // Phase 11 — Mission accept/decline
    Route::prefix('provider/assignments')->group(function () {
        Route::get('/inbox',                 [ProviderMissionAssignmentController::class, 'inbox']);
        Route::get('/{assignment}',          [ProviderMissionAssignmentController::class, 'show']);
        Route::post('/{assignment}/accept',  [ProviderMissionAssignmentController::class, 'accept']);
        Route::post('/{assignment}/decline', [ProviderMissionAssignmentController::class, 'decline']);
    });

    // Phase 12 — Mission lifecycle (start/arrive/complete)
    Route::prefix('provider/missions')->group(function () {
        Route::get('/active',                [ProviderMissionLifecycleController::class, 'active']);
        Route::get('/{mission}',             [ProviderMissionLifecycleController::class, 'show']);
        Route::post('/{mission}/start',      [ProviderMissionLifecycleController::class, 'start']);
        Route::post('/{mission}/arrive',     [ProviderMissionLifecycleController::class, 'arrive']);
        Route::post('/{mission}/complete',   [ProviderMissionLifecycleController::class, 'complete']);
    });


    // Phase 14 — Cancellation client
    Route::prefix('client/bookings')->group(function () {
        Route::get('/{booking}/cancellation-quote', [CancellationController::class, 'quote']);
        Route::post('/{booking}/cancel-with-fee',   [CancellationController::class, 'cancelWithFee']);
    });

    // Phase 14 — Cancellation provider
    Route::prefix('provider/missions')->group(function () {
        Route::post('/{mission}/cancel',   [ProviderCancellationController::class, 'cancel']);
        Route::post('/{mission}/no-show',  [ProviderCancellationController::class, 'noShow']);
    });

    // Phase 14 — Onboarding provider
    Route::prefix('provider/onboarding')->group(function () {
        Route::post('/start',     [ProviderOnboardingController::class, 'start']);
        Route::get('/progress',   [ProviderOnboardingController::class, 'progress']);
        Route::post('/profile',   [ProviderOnboardingController::class, 'setProfile']);
        Route::post('/documents', [ProviderOnboardingController::class, 'uploadDocument']);
        Route::post('/tax',       [ProviderOnboardingController::class, 'setTax']);
        Route::post('/skills',    [ProviderOnboardingController::class, 'setSkills']);
    });
});


Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/onboarding-documents/{document}/file', function (
        \App\Models\ProviderOnboardingDocument $document
    ) {
        return response()->file(
            storage_path('app/private/' . $document->file_path)
        );
    })->name('admin.onboarding.document.file');
});

Route::middleware(['auth:sanctum'])->prefix('provider')->group(function () {
    Route::get('/payouts', [ProviderPayoutsController::class, 'index']);
    Route::get('/payouts/summary', [ProviderPayoutsController::class, 'summary']);
});