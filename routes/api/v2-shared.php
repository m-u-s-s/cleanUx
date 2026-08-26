<?php

use App\Http\Controllers\Api\ApiTokensV2Controller;
use App\Http\Controllers\Api\CancellationV2Controller;
use App\Http\Controllers\Api\ChatV2Controller;
use App\Http\Controllers\Api\ContractsV2Controller;
use App\Http\Controllers\Api\FleetV2Controller;
use App\Http\Controllers\Api\GeolocationV2Controller;
use App\Http\Controllers\Api\KybV2Controller;
use App\Http\Controllers\Api\OnboardingV2Controller;
use App\Http\Controllers\Api\SubscriptionsV2Controller;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — v2 shared routes (not admin/client/provider-specific)
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Phase Onboarding v2 — Journeys orchestration (client/provider/enterprise)
    Route::prefix('v2/onboarding')->group(function () {
        Route::get('/me', [OnboardingV2Controller::class, 'me']);
        Route::post('/steps/{step}/complete', [OnboardingV2Controller::class, 'completeStep']);
        Route::post('/steps/{step}/skip', [OnboardingV2Controller::class, 'skipStep']);
    });

    // Phase Contracts v2 — Templates + documents + signatures (user-facing)
    Route::prefix('v2/contracts')->group(function () {
        Route::get('/templates', [ContractsV2Controller::class, 'activeTemplates']);
        Route::post('/documents', [ContractsV2Controller::class, 'renderDocument']);
        Route::get('/documents/{document}', [ContractsV2Controller::class, 'showDocument']);
        Route::post('/documents/{document}/sign', [ContractsV2Controller::class, 'signDocument']);
        Route::get('/documents/{document}/pdf', [ContractsV2Controller::class, 'downloadPdf']);
    });

    // Phase Fleet v2 — User-facing (my assignments + available)
    Route::prefix('v2/fleet')->group(function () {
        Route::get('/me/assignments', [FleetV2Controller::class, 'listMyAssignments']);
        Route::post('/assignments/{assignment}/return', [FleetV2Controller::class, 'returnAssignment']);
        Route::get('/available', [FleetV2Controller::class, 'findAvailable']);
    });

    // Phase KYB v2 — User-facing entity verification
    Route::prefix('v2/kyb')->group(function () {
        Route::get('/me/entities', [KybV2Controller::class, 'listMyEntities']);
        Route::post('/me/entities', [KybV2Controller::class, 'startVerification']);
        Route::get('/me/entities/{entity}', [KybV2Controller::class, 'showMyEntity']);
        Route::post('/me/entities/{entity}/documents', [KybV2Controller::class, 'uploadDocument']);
        Route::get('/documents/{document}/download', [KybV2Controller::class, 'downloadDocument']);
    });

    // Phase Subscriptions v2 — User-facing plans + self-management
    Route::prefix('v2/subscriptions')->group(function () {
        Route::get('/plans', [SubscriptionsV2Controller::class, 'listPlans']);
        Route::get('/me', [SubscriptionsV2Controller::class, 'listMySubscriptions']);
        Route::post('/me', [SubscriptionsV2Controller::class, 'subscribe']);
        Route::post('/me/{subscription}/pause', [SubscriptionsV2Controller::class, 'pause']);
        Route::post('/me/{subscription}/resume', [SubscriptionsV2Controller::class, 'resume']);
        Route::post('/me/{subscription}/cancel', [SubscriptionsV2Controller::class, 'cancel']);
        Route::post('/me/{subscription}/change-plan', [SubscriptionsV2Controller::class, 'changePlan']);
        Route::get('/me/{subscription}/cycles', [SubscriptionsV2Controller::class, 'listMyCycles']);
    });

    // Phase Chat v2 — In-app messaging (user-facing)
    Route::prefix('v2/chat')->group(function () {
        Route::get('/threads', [ChatV2Controller::class, 'listMyThreads']);
        Route::post('/threads', [ChatV2Controller::class, 'createThread']);
        Route::get('/threads/{thread}', [ChatV2Controller::class, 'showThread']);
        Route::get('/threads/{thread}/messages', [ChatV2Controller::class, 'listMessages']);
        Route::post('/threads/{thread}/messages', [ChatV2Controller::class, 'sendMessage'])->middleware('throttle:chat');
        Route::post('/threads/{thread}/read', [ChatV2Controller::class, 'markAsRead']);
        Route::post('/threads/{thread}/archive', [ChatV2Controller::class, 'archiveThread']);
        // Composition du fil : ajouter quelqu'un de lié, ou l'en retirer. `left_at` existait dans le
        // schéma sans qu'aucune route ne puisse l'écrire.
        Route::post('/threads/{thread}/participants', [ChatV2Controller::class, 'addParticipants']);
        Route::delete('/threads/{thread}/participants/{userId}', [ChatV2Controller::class, 'removeParticipant'])
            ->whereNumber('userId');
        Route::get('/messages/{message}/attachment', [ChatV2Controller::class, 'downloadAttachment']);
    });

    // Phase API Tokens v2 — Self-service tokens + scopes catalog
    Route::prefix('v2/tokens')->group(function () {
        Route::get('/scopes', [ApiTokensV2Controller::class, 'scopesCatalog']);
        Route::get('/me/tokens', [ApiTokensV2Controller::class, 'listMyTokens']);
        Route::post('/me/tokens', [ApiTokensV2Controller::class, 'createMyToken']);
        Route::post('/me/tokens/{token}/rotate', [ApiTokensV2Controller::class, 'rotateMyToken']);
        Route::delete('/me/tokens/{token}', [ApiTokensV2Controller::class, 'revokeMyToken']);
    });

    // Phase Geolocation v2 — Address autocomplete + geocoding + distance (user-facing)
    Route::prefix('v2/geo')->group(function () {
        Route::get('/autocomplete', [GeolocationV2Controller::class, 'autocomplete']);
        Route::get('/geocode', [GeolocationV2Controller::class, 'geocode']);
        Route::get('/reverse', [GeolocationV2Controller::class, 'reverse']);
        Route::post('/distance', [GeolocationV2Controller::class, 'distance']);
    });

    // Phase Cancellation v2 — Quote + execute (client/provider)
    Route::prefix('v2/client/bookings')->group(function () {
        /*
         * LE QUESTIONNAIRE AVANT LE DEVIS, ET DANS CET ORDRE.
         *
         * L'ecran demande d'abord ce qui se passe, puis chiffre. Sans cette route il devrait
         * connaitre les codes de motif en dur : la premiere option qu'un administrateur ajoute
         * serait invisible, et la premiere qu'il retire produirait un code que le moteur ne
         * reconnait plus.
         */
        Route::get('/{booking}/cancellation-questionnaire', [CancellationV2Controller::class, 'clientQuestionnaire']);
        Route::get('/{booking}/cancellation-quote', [CancellationV2Controller::class, 'clientQuote']);
        Route::post('/{booking}/cancel', [CancellationV2Controller::class, 'clientExecute']);
    });
    Route::prefix('v2/provider/bookings')->group(function () {
        Route::get('/{booking}/cancellation-questionnaire', [CancellationV2Controller::class, 'providerQuestionnaire']);
        Route::get('/{booking}/cancellation-quote', [CancellationV2Controller::class, 'providerQuote']);
        Route::post('/{booking}/cancel', [CancellationV2Controller::class, 'providerExecute']);
    });
});
