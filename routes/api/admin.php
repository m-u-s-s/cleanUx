<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — Admin endpoints
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Phase Matching v2 — Simulation admin
    Route::prefix('admin/matching')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/bookings/{booking}/simulate', [\App\Http\Controllers\Api\Admin\MatchingSimulationController::class, 'simulate']);
    });

    // Phase Risk v2 — Évaluations + holds + review (admin)
    Route::prefix('admin/risk')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/evaluations',               [\App\Http\Controllers\Api\Admin\RiskController::class, 'evaluations']);
        Route::get('/holds',                     [\App\Http\Controllers\Api\Admin\RiskController::class, 'holds']);
        Route::post('/holds/{hold}/review',      [\App\Http\Controllers\Api\Admin\RiskController::class, 'reviewHold']);
    });

    // Phase Onboarding v2 — Admin progress index
    Route::prefix('admin/onboarding-v2')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/progress', [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'adminIndex']);
    });

    // Phase Pricing v2 — Admin quotes listing
    Route::prefix('admin/pricing-v2')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/quotes', [\App\Http\Controllers\Api\PricingV2Controller::class, 'adminQuotes']);
    });

    // Phase Contracts v2 — Admin templates + documents + signature invalidation
    Route::prefix('admin/contracts-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/templates',                                  [\App\Http\Controllers\Api\ContractsV2Controller::class, 'adminTemplates']);
        Route::get('/documents',                                  [\App\Http\Controllers\Api\ContractsV2Controller::class, 'adminDocuments']);
        Route::post('/signatures/{signature}/invalidate',         [\App\Http\Controllers\Api\ContractsV2Controller::class, 'adminInvalidateSignature']);
    });

    // Phase Fleet v2 — Admin CRUD vehicles/equipment/assignments/maintenance/certifications
    Route::prefix('admin/fleet-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/vehicles',                                [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminListVehicles']);
        Route::post('/vehicles',                               [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminCreateVehicle']);
        Route::post('/vehicles/{vehicle}/assign',              [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminAssignVehicle']);
        Route::get('/equipment',                               [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminListEquipment']);
        Route::post('/equipment',                              [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminCreateEquipment']);
        Route::post('/equipment/{equipment}/assign',           [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminAssignEquipment']);
        Route::get('/assignments',                             [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminListAssignments']);
        Route::post('/maintenance',                            [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminLogMaintenance']);
        Route::get('/maintenance',                             [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminListMaintenanceLogs']);
        Route::get('/certifications',                          [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminListCertifications']);
        Route::post('/certifications',                         [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminAddCertification']);
        Route::post('/certifications/scan-expiring',           [\App\Http\Controllers\Api\FleetV2Controller::class, 'adminScanExpiring']);
    });

    // Phase KYB v2 — Admin entity management
    Route::prefix('admin/kyb-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/entities',                                 [\App\Http\Controllers\Api\KybV2Controller::class, 'adminListEntities']);
        Route::post('/entities/{entity}/run-verifications',     [\App\Http\Controllers\Api\KybV2Controller::class, 'adminRunVerifications']);
        Route::post('/entities/{entity}/run-sanctions',         [\App\Http\Controllers\Api\KybV2Controller::class, 'adminRunSanctions']);
        Route::post('/entities/{entity}/approve',               [\App\Http\Controllers\Api\KybV2Controller::class, 'adminApprove']);
        Route::post('/entities/{entity}/reject',                [\App\Http\Controllers\Api\KybV2Controller::class, 'adminReject']);
        Route::post('/entities/{entity}/beneficial-owners',     [\App\Http\Controllers\Api\KybV2Controller::class, 'adminAddBeneficialOwner']);
        Route::get('/documents',                                [\App\Http\Controllers\Api\KybV2Controller::class, 'adminListDocuments']);
        Route::post('/documents/{document}/review',             [\App\Http\Controllers\Api\KybV2Controller::class, 'adminReviewDocument']);
    });


    // Phase Accounting v2 — Ledger + exports + period management (critical: close/delete)
    Route::prefix('admin/accounting-v2')->middleware('api_scope:admin:critical,admin:everything')->group(function () {
        Route::get('/entries',                              [\App\Http\Controllers\Api\AccountingV2Controller::class, 'listEntries']);
        Route::post('/entries',                             [\App\Http\Controllers\Api\AccountingV2Controller::class, 'postEntries']);
        Route::get('/account-balance',                      [\App\Http\Controllers\Api\AccountingV2Controller::class, 'accountBalance']);
        Route::get('/periods',                              [\App\Http\Controllers\Api\AccountingV2Controller::class, 'listPeriods']);
        Route::post('/periods/{year}/{month}/close',        [\App\Http\Controllers\Api\AccountingV2Controller::class, 'closePeriod']);
        Route::post('/periods/{period}/reopen',             [\App\Http\Controllers\Api\AccountingV2Controller::class, 'reopenPeriod']);
        Route::get('/exports',                              [\App\Http\Controllers\Api\AccountingV2Controller::class, 'listExports']);
        Route::post('/exports',                             [\App\Http\Controllers\Api\AccountingV2Controller::class, 'generateExport']);
        Route::get('/exports/{export}/download',            [\App\Http\Controllers\Api\AccountingV2Controller::class, 'downloadExport']);

        // Period pre-validation + delete entry guard
        Route::get('/periods/{period}/validate', [\App\Http\Controllers\Api\AccountingV2Controller::class, 'validatePeriod']);
        Route::delete('/entries/{entry}',         [\App\Http\Controllers\Api\AccountingV2Controller::class, 'deleteEntry']);
    });

    // Phase Subscriptions v2 — Admin subscriptions/cycles management (critical: force-cancel)
    Route::prefix('admin/subscriptions-v2')->middleware('api_scope:admin:critical,admin:everything')->group(function () {
        Route::get('/subscriptions',                        [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminListSubscriptions']);
        Route::get('/cycles',                               [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminListCycles']);
        Route::post('/cycles/{cycle}/retry-billing',        [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminRetryBilling']);
        Route::post('/subscriptions/{subscription}/force-cancel', [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminForceCancel']);
    });

    // Phase Chat v2 — Admin moderation
    Route::prefix('admin/chat-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/threads',                              [\App\Http\Controllers\Api\ChatV2Controller::class, 'adminListThreads']);
        Route::get('/flagged',                              [\App\Http\Controllers\Api\ChatV2Controller::class, 'adminListFlagged']);
        Route::post('/messages/{message}/moderate',         [\App\Http\Controllers\Api\ChatV2Controller::class, 'adminModerate']);
    });

    // Phase API Tokens v2 — Admin token management (critical: revoke/delete)
    Route::prefix('admin/api-tokens-v2')->middleware('api_scope:admin:critical,admin:everything')->group(function () {
        Route::get('/tokens',                       [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminListTokens']);
        Route::get('/usages',                       [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminListUsages']);
        Route::post('/tokens/{token}/suspend',      [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminSuspend']);
        Route::post('/tokens/{token}/unsuspend',    [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminUnsuspend']);
        Route::delete('/tokens/{token}',            [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminRevoke']);
    });

    // Phase Geolocation v2 — Admin lookups/stats/cache purge
    Route::prefix('admin/geolocation-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/lookups',       [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'adminLookups']);
        Route::get('/stats',         [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'adminStats']);
        Route::post('/cache/purge',  [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'adminPurgeCache']);
    });

    // Phase Webhooks v2 — Outbound B2B endpoints + deliveries + events
    Route::prefix('admin/webhooks-v2')->middleware('api_scope:admin:webhooks,admin:everything')->group(function () {
        Route::get('/endpoints',                            [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminListEndpoints']);
        Route::post('/endpoints',                           [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminCreateEndpoint']);
        Route::patch('/endpoints/{endpoint}',               [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminUpdateEndpoint']);
        Route::post('/endpoints/{endpoint}/rotate-secret',  [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminRotateSecret']);
        Route::post('/endpoints/{endpoint}/test',           [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminTestEndpoint']);
        Route::delete('/endpoints/{endpoint}',              [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminDeleteEndpoint']);
        Route::get('/events',                               [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminListEvents']);
        Route::get('/deliveries',                           [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminListDeliveries']);
        Route::post('/deliveries/{delivery}/replay',        [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminReplayDelivery']);

        // Dead-letter a delivery
        Route::post('/deliveries/{delivery}/dead-letter', [\App\Http\Controllers\Api\WebhooksV2Controller::class, 'adminDeadLetter']);
    });

    // Phase Audit v2 — Events search / pin / export
    Route::prefix('admin/audit')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/events',                    [\App\Http\Controllers\Api\Admin\AuditController::class, 'index']);
        Route::get('/events/export',             [\App\Http\Controllers\Api\Admin\AuditController::class, 'export']);
        Route::get('/events/{event}',            [\App\Http\Controllers\Api\Admin\AuditController::class, 'show']);
        Route::post('/events/{event}/pin',       [\App\Http\Controllers\Api\Admin\AuditController::class, 'pin']);
        Route::post('/events/{event}/unpin',     [\App\Http\Controllers\Api\Admin\AuditController::class, 'unpin']);
    });

    // Dispute resolution — admin resolve / escalate
    Route::prefix('admin/disputes')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::post('/{dispute}/resolve',  [\App\Http\Controllers\Api\Admin\DisputeAdminController::class, 'resolve']);
        Route::post('/{dispute}/escalate', [\App\Http\Controllers\Api\Admin\DisputeAdminController::class, 'escalate']);
    });

    // Auto-dispatch — admin triggers scored dispatch for a booking
    Route::prefix('admin/bookings')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::post('/{booking}/dispatch', [\App\Http\Controllers\Api\Admin\BookingDispatchController::class, 'dispatch']);
    });

    // Cancellation v2 — Admin override
    Route::prefix('admin/cancellations-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/',                              [\App\Http\Controllers\Api\CancellationV2Controller::class, 'adminIndex']);
        Route::post('/{cancellation}/override',      [\App\Http\Controllers\Api\CancellationV2Controller::class, 'adminOverride']);
    });

    // Marketing v2 — Admin CRUD campaigns + segments (critical: delete campaigns/segments)
    Route::prefix('admin/marketing')->middleware('api_scope:admin:critical,admin:everything')->group(function () {

        // Campaigns
        Route::get('/campaigns',                               [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'index']);
        Route::post('/campaigns',                              [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'store']);
        Route::put('/campaigns/{campaign}',                    [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'update']);
        Route::delete('/campaigns/{campaign}',                 [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'destroy']);
        Route::post('/campaigns/{campaign}/schedule',          [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'schedule']);
        Route::post('/campaigns/{campaign}/pause',             [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'pause']);
        Route::post('/campaigns/{campaign}/resume',            [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'resume']);
        Route::post('/campaigns/{campaign}/cancel',            [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'cancel']);
        Route::get('/campaigns/{campaign}/stats',              [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'stats']);

        // Campaign step CRUD
        Route::post('/campaigns/{campaign}/steps',             [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'storeStep']);
        Route::put('/campaigns/{campaign}/steps/{step}',       [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'updateStep']);
        Route::delete('/campaigns/{campaign}/steps/{step}',    [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'destroyStep']);

        // A/B test config
        Route::patch('/campaigns/{campaign}/ab-config',        [\App\Http\Controllers\Api\Admin\MarketingCampaignController::class, 'updateAbConfig']);

        // Segments
        Route::get('/segments',                                [\App\Http\Controllers\Api\Admin\MarketingSegmentController::class, 'index']);
        Route::post('/segments',                               [\App\Http\Controllers\Api\Admin\MarketingSegmentController::class, 'store']);
        Route::put('/segments/{segment}',                      [\App\Http\Controllers\Api\Admin\MarketingSegmentController::class, 'update']);
        Route::delete('/segments/{segment}',                   [\App\Http\Controllers\Api\Admin\MarketingSegmentController::class, 'destroy']);
        Route::post('/segments/{segment}/recompute',           [\App\Http\Controllers\Api\Admin\MarketingSegmentController::class, 'recompute']);
    });

    // Insurance v2 — Admin claim status machine + claims list
    Route::prefix('admin/insurance-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::patch('/claims/{claim}/status', [\App\Http\Controllers\Api\Admin\InsuranceAdminController::class, 'updateClaimStatus']);
        Route::get('/claims',                  [\App\Http\Controllers\Api\Admin\InsuranceAdminController::class, 'claims']);
    });
});
