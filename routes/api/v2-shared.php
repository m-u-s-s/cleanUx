<?php

use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — v2 shared routes (not admin/client/provider-specific)
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Phase Onboarding v2 — Journeys orchestration (client/provider/enterprise)
    Route::prefix('v2/onboarding')->group(function () {
        Route::get('/me',                        [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'me']);
        Route::post('/steps/{step}/complete',    [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'completeStep']);
        Route::post('/steps/{step}/skip',        [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'skipStep']);
    });

    // Phase Contracts v2 — Templates + documents + signatures (user-facing)
    Route::prefix('v2/contracts')->group(function () {
        Route::get('/templates',                        [\App\Http\Controllers\Api\ContractsV2Controller::class, 'activeTemplates']);
        Route::post('/documents',                       [\App\Http\Controllers\Api\ContractsV2Controller::class, 'renderDocument']);
        Route::get('/documents/{document}',             [\App\Http\Controllers\Api\ContractsV2Controller::class, 'showDocument']);
        Route::post('/documents/{document}/sign',       [\App\Http\Controllers\Api\ContractsV2Controller::class, 'signDocument']);
        Route::get('/documents/{document}/pdf',         [\App\Http\Controllers\Api\ContractsV2Controller::class, 'downloadPdf']);
    });

    // Phase Fleet v2 — User-facing (my assignments + available)
    Route::prefix('v2/fleet')->group(function () {
        Route::get('/me/assignments',                          [\App\Http\Controllers\Api\FleetV2Controller::class, 'listMyAssignments']);
        Route::post('/assignments/{assignment}/return',        [\App\Http\Controllers\Api\FleetV2Controller::class, 'returnAssignment']);
        Route::get('/available',                               [\App\Http\Controllers\Api\FleetV2Controller::class, 'findAvailable']);
    });

    // Phase KYB v2 — User-facing entity verification
    Route::prefix('v2/kyb')->group(function () {
        Route::get('/me/entities',                              [\App\Http\Controllers\Api\KybV2Controller::class, 'listMyEntities']);
        Route::post('/me/entities',                             [\App\Http\Controllers\Api\KybV2Controller::class, 'startVerification']);
        Route::get('/me/entities/{entity}',                     [\App\Http\Controllers\Api\KybV2Controller::class, 'showMyEntity']);
        Route::post('/me/entities/{entity}/documents',          [\App\Http\Controllers\Api\KybV2Controller::class, 'uploadDocument']);
        Route::get('/documents/{document}/download',            [\App\Http\Controllers\Api\KybV2Controller::class, 'downloadDocument']);
    });

    // Phase Tenancy v2 — Current tenant (user-facing)
    Route::prefix('v2/tenancy')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\TenancyV2Controller::class, 'currentTenant']);
    });

    // Phase Subscriptions v2 — User-facing plans + self-management
    Route::prefix('v2/subscriptions')->group(function () {
        Route::get('/plans',                                [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'listPlans']);
        Route::get('/me',                                   [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'listMySubscriptions']);
        Route::post('/me',                                  [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'subscribe']);
        Route::post('/me/{subscription}/pause',             [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'pause']);
        Route::post('/me/{subscription}/resume',            [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'resume']);
        Route::post('/me/{subscription}/cancel',            [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'cancel']);
        Route::post('/me/{subscription}/change-plan',       [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'changePlan']);
        Route::get('/me/{subscription}/cycles',             [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'listMyCycles']);
    });

    // Phase Chat v2 — In-app messaging (user-facing)
    Route::prefix('v2/chat')->group(function () {
        Route::get('/threads',                              [\App\Http\Controllers\Api\ChatV2Controller::class, 'listMyThreads']);
        Route::post('/threads',                             [\App\Http\Controllers\Api\ChatV2Controller::class, 'createThread']);
        Route::get('/threads/{thread}',                     [\App\Http\Controllers\Api\ChatV2Controller::class, 'showThread']);
        Route::get('/threads/{thread}/messages',            [\App\Http\Controllers\Api\ChatV2Controller::class, 'listMessages']);
        Route::post('/threads/{thread}/messages',           [\App\Http\Controllers\Api\ChatV2Controller::class, 'sendMessage'])->middleware('throttle:chat');
        Route::post('/threads/{thread}/read',               [\App\Http\Controllers\Api\ChatV2Controller::class, 'markAsRead']);
        Route::post('/threads/{thread}/archive',            [\App\Http\Controllers\Api\ChatV2Controller::class, 'archiveThread']);
        Route::get('/messages/{message}/attachment',        [\App\Http\Controllers\Api\ChatV2Controller::class, 'downloadAttachment']);
    });

    // Phase API Tokens v2 — Self-service tokens + scopes catalog
    Route::prefix('v2/tokens')->group(function () {
        Route::get('/scopes',                  [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'scopesCatalog']);
        Route::get('/me/tokens',               [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'listMyTokens']);
        Route::post('/me/tokens',              [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'createMyToken']);
        Route::post('/me/tokens/{token}/rotate', [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'rotateMyToken']);
        Route::delete('/me/tokens/{token}',    [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'revokeMyToken']);
    });

    // Phase Geolocation v2 — Address autocomplete + geocoding + distance (user-facing)
    Route::prefix('v2/geo')->group(function () {
        Route::get('/autocomplete',  [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'autocomplete']);
        Route::get('/geocode',       [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'geocode']);
        Route::get('/reverse',       [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'reverse']);
        Route::post('/distance',     [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'distance']);
    });

    // Phase Cancellation v2 — Quote + execute (client/provider)
    Route::prefix('v2/client/bookings')->group(function () {
        Route::get('/{booking}/cancellation-quote',  [\App\Http\Controllers\Api\CancellationV2Controller::class, 'clientQuote']);
        Route::post('/{booking}/cancel',             [\App\Http\Controllers\Api\CancellationV2Controller::class, 'clientExecute']);
    });
    Route::prefix('v2/provider/bookings')->group(function () {
        Route::get('/{booking}/cancellation-quote',  [\App\Http\Controllers\Api\CancellationV2Controller::class, 'providerQuote']);
        Route::post('/{booking}/cancel',             [\App\Http\Controllers\Api\CancellationV2Controller::class, 'providerExecute']);
    });
});
