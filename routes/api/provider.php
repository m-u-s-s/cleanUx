<?php

use App\Http\Controllers\Api\EmployeeMissionTrackingController;
use App\Http\Controllers\Api\Provider\ProviderCancellationController;
use App\Http\Controllers\Api\Provider\ProviderMissionLifecycleController;
use App\Http\Controllers\Api\Provider\ProviderOnboardingController;
use App\Http\Controllers\Api\Provider\ProviderPayoutsController;
use App\Http\Controllers\Api\ProviderMissionAssignmentController;
use App\Http\Controllers\Api\ProviderPresenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — Provider endpoints
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Phase 0 — Mission tracking (existant)
    Route::post('/missions/{mission}/tracking/start',           [EmployeeMissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/push',    [EmployeeMissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/stop',    [EmployeeMissionTrackingController::class, 'stop']);

    // Phase 11 — Provider presence (binary on/off)
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

    // Phase Ratings + Performance + Live Tracking + Trip Tracking + Badges + Presence v2 + Quality + Availability + Wallet + Disputes + KYC + Profile + Stripe Connect
    Route::prefix('provider')->group(function () {

        // Phase Ratings — Avis provider → client + réponse aux avis reçus
        Route::get('/ratings/me',                     [\App\Http\Controllers\Api\Provider\ProviderRatingController::class, 'mine']);
        Route::post('/bookings/{booking}/rating',     [\App\Http\Controllers\Api\Provider\ProviderRatingController::class, 'submit']);
        Route::post('/ratings/{feedback}/respond',    [\App\Http\Controllers\Api\Provider\ProviderRatingController::class, 'respond']);

        // Phase Matching v2 — Performance metrics du provider
        Route::get('/performance/me',                 [\App\Http\Controllers\Api\Provider\ProviderPerformanceController::class, 'me']);

        // Phase Realtime v2 — Live position / ETA broadcasts pendant une mission
        Route::post('/missions/{mission}/live/position', [\App\Http\Controllers\Api\Provider\MissionLiveTrackingController::class, 'pushPosition']);
        Route::post('/missions/{mission}/live/eta',      [\App\Http\Controllers\Api\Provider\MissionLiveTrackingController::class, 'pushEta']);

        // Trip Tracking v2 — sessions GPS persistées + auto-ETA + geofence
        Route::post('/bookings/{booking}/tracking/start',        [\App\Http\Controllers\Api\Provider\TripTrackingController::class, 'start']);
        Route::post('/tracking/{session}/ping',                  [\App\Http\Controllers\Api\Provider\TripTrackingController::class, 'ping']);
        Route::post('/tracking/{session}/in-mission',            [\App\Http\Controllers\Api\Provider\TripTrackingController::class, 'markInMission']);
        Route::post('/tracking/{session}/end',                   [\App\Http\Controllers\Api\Provider\TripTrackingController::class, 'end']);

        // Provider Badges (read + manual re-evaluate)
        Route::get('/badges',              [\App\Http\Controllers\Api\Provider\BadgesController::class, 'mine']);
        Route::post('/badges/evaluate',    [\App\Http\Controllers\Api\Provider\BadgesController::class, 'evaluate']);

        // Presence v2 — Online/Busy/Break/Offline (4 états, coexiste avec Phase 11 binary on/off)
        Route::get('/presence-v2',            [\App\Http\Controllers\Api\Provider\PresenceController::class, 'status']);
        Route::post('/presence-v2/online',    [\App\Http\Controllers\Api\Provider\PresenceController::class, 'goOnline']);
        Route::post('/presence-v2/heartbeat', [\App\Http\Controllers\Api\Provider\PresenceController::class, 'heartbeat']);
        Route::post('/presence-v2/break',     [\App\Http\Controllers\Api\Provider\PresenceController::class, 'goBreak']);
        Route::post('/presence-v2/offline',   [\App\Http\Controllers\Api\Provider\PresenceController::class, 'goOffline']);

        // Phase Quality v2 — Inspections (provider terrain)
        Route::get('/missions/{mission}/inspections',                 [\App\Http\Controllers\Api\Provider\QualityInspectionController::class, 'index']);
        Route::post('/missions/{mission}/inspections',                [\App\Http\Controllers\Api\Provider\QualityInspectionController::class, 'start']);
        Route::get('/inspections/{inspection}',                       [\App\Http\Controllers\Api\Provider\QualityInspectionController::class, 'show']);
        Route::put('/inspections/{inspection}/items/{checklistItem}', [\App\Http\Controllers\Api\Provider\QualityInspectionController::class, 'submitItem']);
        Route::post('/inspections/{inspection}/photos',               [\App\Http\Controllers\Api\Provider\QualityInspectionController::class, 'uploadPhoto']);
        Route::post('/inspections/{inspection}/submit',               [\App\Http\Controllers\Api\Provider\QualityInspectionController::class, 'submit']);

        // Phase Availability v2 — Calendrier provider (slots récurrents + exceptions + iCal)
        Route::get('/availability',                          [\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'index']);
        Route::get('/availability/windows',                  [\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'windows']);
        Route::get('/availability/ical',                     [\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'ical']);
        Route::post('/availability/slots',                   [\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'storeSlot']);
        Route::put('/availability/slots/{slot}',             [\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'updateSlot']);
        Route::delete('/availability/slots/{slot}',          [\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'destroySlot']);
        Route::post('/availability/exceptions',              [\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'storeException']);
        Route::delete('/availability/exceptions/{exception}',[\App\Http\Controllers\Api\Provider\AvailabilityController::class, 'destroyException']);

        // Phase Stripe v2 — Wallet provider
        Route::get('/wallet/balance',                 [\App\Http\Controllers\Api\Provider\ProviderWalletController::class, 'balance']);
        Route::get('/wallet/transactions',            [\App\Http\Controllers\Api\Provider\ProviderWalletController::class, 'transactions']);
        Route::post('/wallet/withdraw',               [\App\Http\Controllers\Api\Provider\ProviderWalletController::class, 'withdraw']);

        // Phase Disputes v2 — Litiges provider
        Route::get('/disputes',                       [\App\Http\Controllers\Api\Provider\ProviderDisputeController::class, 'index']);
        Route::post('/disputes/{dispute}/respond',    [\App\Http\Controllers\Api\Provider\ProviderDisputeController::class, 'respond']);

        // Phase KYC v2 — Vérification d'identité
        Route::post('/kyc/start',                     [\App\Http\Controllers\Api\Provider\KycController::class, 'start']);
        Route::get('/kyc/status',                     [\App\Http\Controllers\Api\Provider\KycController::class, 'status']);
        Route::post('/kyc/verifications/{verification}/sync', [\App\Http\Controllers\Api\Provider\KycController::class, 'sync']);

        // Mobile app — provider profile self-update (name, phone, locale)
        Route::put('/profile', [\App\Http\Controllers\Api\Provider\ProviderProfileController::class, 'update']);

        // Sprint 0 — Task 3: Stripe Connect provider endpoints (RN Phase 2)
        Route::prefix('stripe-connect')->middleware('token.grace')->group(function () {
            Route::get('/status',         [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'status']);
            Route::post('/onboard',       [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'onboard']);
            Route::get('/payouts',        [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'payouts']);
            Route::get('/dashboard-link', [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'dashboardLink']);
        });

        // Fleet v2 — Provider my-vehicles
        Route::get('/fleet/my-vehicles',                          [\App\Http\Controllers\Api\Provider\FleetProviderController::class, 'myVehicles']);

        // Fleet — Provider return assignment
        Route::post('/fleet/assignments/{assignment}/return',     [\App\Http\Controllers\Api\Provider\FleetProviderController::class, 'returnAssignment']);
    });

    // Phase 12 — Mission lifecycle (start/arrive/complete)
    Route::prefix('provider/missions')->group(function () {
        Route::get('/active',                [ProviderMissionLifecycleController::class, 'active']);
        Route::get('/{mission}',             [ProviderMissionLifecycleController::class, 'show']);
        Route::post('/{mission}/start',      [ProviderMissionLifecycleController::class, 'start']);
        Route::post('/{mission}/arrive',     [ProviderMissionLifecycleController::class, 'arrive']);
        Route::post('/{mission}/complete',   [ProviderMissionLifecycleController::class, 'complete']);
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

// Payouts — token.grace middleware
Route::middleware(['auth:sanctum', 'token.grace'])->prefix('provider')->group(function () {
    Route::get('/payouts',         [ProviderPayoutsController::class, 'index']);
    Route::get('/payouts/summary', [ProviderPayoutsController::class, 'summary']);
});

// Admin — Onboarding document file download (web session auth + role:admin)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/onboarding-documents/{document}/file', [\App\Http\Controllers\Api\Provider\ProviderOnboardingController::class, 'downloadDocument'])
        ->name('admin.onboarding.document.file');
});
