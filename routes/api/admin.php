<?php

use App\Http\Controllers\Api\AccountingV2Controller;
use App\Http\Controllers\Api\Admin\AdminCatalogController;
use App\Http\Controllers\Api\Admin\AdminOverviewController;
use App\Http\Controllers\Api\Admin\AuditController;
use App\Http\Controllers\Api\Admin\BookingDispatchController;
use App\Http\Controllers\Api\Admin\Console\ReportController;
use App\Http\Controllers\Api\Admin\Console\ResourceController;
use App\Http\Controllers\Api\Admin\DisputeAdminController;
use App\Http\Controllers\Api\Admin\InsuranceAdminController;
use App\Http\Controllers\Api\Admin\JourneyBuilderController;
use App\Http\Controllers\Api\Admin\MarketingCampaignController;
use App\Http\Controllers\Api\Admin\MarketingSegmentController;
use App\Http\Controllers\Api\Admin\MatchingSimulationController;
use App\Http\Controllers\Api\Admin\RiskController;
use App\Http\Controllers\Api\Admin\ZoneCatalogController;
use App\Http\Controllers\Api\ApiTokensV2Controller;
use App\Http\Controllers\Api\CancellationV2Controller;
use App\Http\Controllers\Api\ChatV2Controller;
use App\Http\Controllers\Api\ContractsV2Controller;
use App\Http\Controllers\Api\FleetV2Controller;
use App\Http\Controllers\Api\GeolocationV2Controller;
use App\Http\Controllers\Api\KybV2Controller;
use App\Http\Controllers\Api\OnboardingV2Controller;
use App\Http\Controllers\Api\PricingV2Controller;
use App\Http\Controllers\Api\SubscriptionsV2Controller;
use App\Http\Controllers\Api\WebhooksV2Controller;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — Admin endpoints
// ─────────────────────────────────────────────

/*
 * `api_scope` dit ce qu'un jeton a le droit de faire ; il ne dit pas QUI le porte.
 *
 * Le jeton mobile est émis sans liste d'abilities, donc Sanctum y inscrit '*', et le contrôle de
 * scope laisse passer tout jeton portant '*'. Ce groupe était par conséquent ouvert à n'importe
 * quel compte authentifié depuis l'application : un client atteignait la comptabilité et les
 * jetons d'API. La garde de rôle est le verrou qui manquait — le scope reste en place, il filtre
 * ce qu'un jeton d'intégration a le droit de faire une fois le rôle établi.
 */
Route::middleware(['auth:sanctum', 'api_admin'])->group(function () {

    // Phase Matching v2 — Simulation admin
    Route::prefix('admin/matching')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/bookings/{booking}/simulate', [MatchingSimulationController::class, 'simulate']);
    });

    // Phase Risk v2 — Évaluations + holds + review (admin)
    Route::prefix('admin/risk')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/evaluations', [RiskController::class, 'evaluations']);
        Route::get('/holds', [RiskController::class, 'holds']);
        Route::post('/holds/{hold}/review', [RiskController::class, 'reviewHold']);
    });

    // Phase Onboarding v2 — Admin progress index
    Route::prefix('admin/onboarding-v2')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/progress', [OnboardingV2Controller::class, 'adminIndex']);
    });

    // Phase Pricing v2 — Admin quotes listing
    Route::prefix('admin/pricing-v2')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/quotes', [PricingV2Controller::class, 'adminQuotes']);
    });

    // Phase Contracts v2 — Admin templates + documents + signature invalidation
    Route::prefix('admin/contracts-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/templates', [ContractsV2Controller::class, 'adminTemplates']);
        Route::get('/documents', [ContractsV2Controller::class, 'adminDocuments']);
        Route::post('/signatures/{signature}/invalidate', [ContractsV2Controller::class, 'adminInvalidateSignature']);
    });

    // Phase Fleet v2 — Admin CRUD vehicles/equipment/assignments/maintenance/certifications
    Route::prefix('admin/fleet-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/vehicles', [FleetV2Controller::class, 'adminListVehicles']);
        Route::post('/vehicles', [FleetV2Controller::class, 'adminCreateVehicle']);
        Route::post('/vehicles/{vehicle}/assign', [FleetV2Controller::class, 'adminAssignVehicle']);
        Route::get('/equipment', [FleetV2Controller::class, 'adminListEquipment']);
        Route::post('/equipment', [FleetV2Controller::class, 'adminCreateEquipment']);
        Route::post('/equipment/{equipment}/assign', [FleetV2Controller::class, 'adminAssignEquipment']);
        Route::get('/assignments', [FleetV2Controller::class, 'adminListAssignments']);
        Route::post('/maintenance', [FleetV2Controller::class, 'adminLogMaintenance']);
        Route::get('/maintenance', [FleetV2Controller::class, 'adminListMaintenanceLogs']);
        Route::get('/certifications', [FleetV2Controller::class, 'adminListCertifications']);
        Route::post('/certifications', [FleetV2Controller::class, 'adminAddCertification']);
        Route::post('/certifications/scan-expiring', [FleetV2Controller::class, 'adminScanExpiring']);
    });

    // Phase KYB v2 — Admin entity management
    Route::prefix('admin/kyb-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/entities', [KybV2Controller::class, 'adminListEntities']);
        Route::post('/entities/{entity}/run-verifications', [KybV2Controller::class, 'adminRunVerifications']);
        Route::post('/entities/{entity}/run-sanctions', [KybV2Controller::class, 'adminRunSanctions']);
        Route::post('/entities/{entity}/approve', [KybV2Controller::class, 'adminApprove']);
        Route::post('/entities/{entity}/reject', [KybV2Controller::class, 'adminReject']);
        Route::post('/entities/{entity}/beneficial-owners', [KybV2Controller::class, 'adminAddBeneficialOwner']);
        Route::get('/documents', [KybV2Controller::class, 'adminListDocuments']);
        Route::post('/documents/{document}/review', [KybV2Controller::class, 'adminReviewDocument']);
    });

    // Phase Accounting v2 — Ledger + exports + period management (critical: close/delete)
    Route::prefix('admin/accounting-v2')->middleware('api_scope:admin:critical,admin:everything')->group(function () {
        Route::get('/entries', [AccountingV2Controller::class, 'listEntries']);
        Route::post('/entries', [AccountingV2Controller::class, 'postEntries']);
        Route::get('/account-balance', [AccountingV2Controller::class, 'accountBalance']);
        Route::get('/periods', [AccountingV2Controller::class, 'listPeriods']);
        Route::post('/periods/{year}/{month}/close', [AccountingV2Controller::class, 'closePeriod']);
        Route::post('/periods/{period}/reopen', [AccountingV2Controller::class, 'reopenPeriod']);
        Route::get('/exports', [AccountingV2Controller::class, 'listExports']);
        Route::post('/exports', [AccountingV2Controller::class, 'generateExport']);
        Route::get('/exports/{export}/download', [AccountingV2Controller::class, 'downloadExport']);

        // Period pre-validation + delete entry guard
        Route::get('/periods/{period}/validate', [AccountingV2Controller::class, 'validatePeriod']);
        Route::delete('/entries/{entry}', [AccountingV2Controller::class, 'deleteEntry']);
    });

    // Phase Subscriptions v2 — Admin subscriptions/cycles management (critical: force-cancel)
    Route::prefix('admin/subscriptions-v2')->middleware('api_scope:admin:critical,admin:everything')->group(function () {
        Route::get('/subscriptions', [SubscriptionsV2Controller::class, 'adminListSubscriptions']);
        Route::get('/cycles', [SubscriptionsV2Controller::class, 'adminListCycles']);
        Route::post('/cycles/{cycle}/retry-billing', [SubscriptionsV2Controller::class, 'adminRetryBilling']);
        Route::post('/subscriptions/{subscription}/force-cancel', [SubscriptionsV2Controller::class, 'adminForceCancel']);
    });

    // Phase Chat v2 — Admin moderation
    Route::prefix('admin/chat-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/threads', [ChatV2Controller::class, 'adminListThreads']);
        Route::get('/flagged', [ChatV2Controller::class, 'adminListFlagged']);
        Route::post('/messages/{message}/moderate', [ChatV2Controller::class, 'adminModerate']);
    });

    // Phase API Tokens v2 — Admin token management (critical: revoke/delete)
    Route::prefix('admin/api-tokens-v2')->middleware('api_scope:admin:critical,admin:everything')->group(function () {
        Route::get('/tokens', [ApiTokensV2Controller::class, 'adminListTokens']);
        Route::get('/usages', [ApiTokensV2Controller::class, 'adminListUsages']);
        Route::post('/tokens/{token}/suspend', [ApiTokensV2Controller::class, 'adminSuspend']);
        Route::post('/tokens/{token}/unsuspend', [ApiTokensV2Controller::class, 'adminUnsuspend']);
        Route::delete('/tokens/{token}', [ApiTokensV2Controller::class, 'adminRevoke']);
    });

    // Phase Geolocation v2 — Admin lookups/stats/cache purge
    Route::prefix('admin/geolocation-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/lookups', [GeolocationV2Controller::class, 'adminLookups']);
        Route::get('/stats', [GeolocationV2Controller::class, 'adminStats']);
        Route::post('/cache/purge', [GeolocationV2Controller::class, 'adminPurgeCache']);
    });

    // Phase Webhooks v2 — Outbound B2B endpoints + deliveries + events
    Route::prefix('admin/webhooks-v2')->middleware('api_scope:admin:webhooks,admin:everything')->group(function () {
        Route::get('/endpoints', [WebhooksV2Controller::class, 'adminListEndpoints']);
        Route::post('/endpoints', [WebhooksV2Controller::class, 'adminCreateEndpoint']);
        Route::patch('/endpoints/{endpoint}', [WebhooksV2Controller::class, 'adminUpdateEndpoint']);
        Route::post('/endpoints/{endpoint}/rotate-secret', [WebhooksV2Controller::class, 'adminRotateSecret']);
        Route::post('/endpoints/{endpoint}/test', [WebhooksV2Controller::class, 'adminTestEndpoint']);
        Route::delete('/endpoints/{endpoint}', [WebhooksV2Controller::class, 'adminDeleteEndpoint']);
        Route::get('/events', [WebhooksV2Controller::class, 'adminListEvents']);
        Route::get('/deliveries', [WebhooksV2Controller::class, 'adminListDeliveries']);
        Route::post('/deliveries/{delivery}/replay', [WebhooksV2Controller::class, 'adminReplayDelivery']);

        // Dead-letter a delivery
        Route::post('/deliveries/{delivery}/dead-letter', [WebhooksV2Controller::class, 'adminDeadLetter']);
    });

    // Phase Audit v2 — Events search / pin / export
    Route::prefix('admin/audit')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/events', [AuditController::class, 'index']);
        Route::get('/events/export', [AuditController::class, 'export']);
        Route::get('/events/{event}', [AuditController::class, 'show']);
        Route::post('/events/{event}/pin', [AuditController::class, 'pin']);
        Route::post('/events/{event}/unpin', [AuditController::class, 'unpin']);
    });

    // Dispute resolution — admin resolve / escalate
    Route::prefix('admin/disputes')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::post('/{dispute}/resolve', [DisputeAdminController::class, 'resolve']);
        Route::post('/{dispute}/escalate', [DisputeAdminController::class, 'escalate']);
    });

    // Auto-dispatch — admin triggers scored dispatch for a booking
    Route::prefix('admin/bookings')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::post('/{booking}/dispatch', [BookingDispatchController::class, 'dispatch']);
    });

    // Cancellation v2 — Admin override
    Route::prefix('admin/cancellations-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::get('/', [CancellationV2Controller::class, 'adminIndex']);
        Route::post('/{cancellation}/override', [CancellationV2Controller::class, 'adminOverride']);
    });

    // Marketing v2 — Admin CRUD campaigns + segments (critical: delete campaigns/segments)
    Route::prefix('admin/marketing')->middleware('api_scope:admin:critical,admin:everything')->group(function () {

        // Campaigns
        Route::get('/campaigns', [MarketingCampaignController::class, 'index']);
        Route::post('/campaigns', [MarketingCampaignController::class, 'store']);
        Route::put('/campaigns/{campaign}', [MarketingCampaignController::class, 'update']);
        Route::delete('/campaigns/{campaign}', [MarketingCampaignController::class, 'destroy']);
        Route::post('/campaigns/{campaign}/schedule', [MarketingCampaignController::class, 'schedule']);
        Route::post('/campaigns/{campaign}/pause', [MarketingCampaignController::class, 'pause']);
        Route::post('/campaigns/{campaign}/resume', [MarketingCampaignController::class, 'resume']);
        Route::post('/campaigns/{campaign}/cancel', [MarketingCampaignController::class, 'cancel']);
        Route::get('/campaigns/{campaign}/stats', [MarketingCampaignController::class, 'stats']);

        // Campaign step CRUD
        Route::post('/campaigns/{campaign}/steps', [MarketingCampaignController::class, 'storeStep']);
        Route::put('/campaigns/{campaign}/steps/{step}', [MarketingCampaignController::class, 'updateStep']);
        Route::delete('/campaigns/{campaign}/steps/{step}', [MarketingCampaignController::class, 'destroyStep']);

        // A/B test config
        Route::patch('/campaigns/{campaign}/ab-config', [MarketingCampaignController::class, 'updateAbConfig']);

        // Segments
        Route::get('/segments', [MarketingSegmentController::class, 'index']);
        Route::post('/segments', [MarketingSegmentController::class, 'store']);
        Route::put('/segments/{segment}', [MarketingSegmentController::class, 'update']);
        Route::delete('/segments/{segment}', [MarketingSegmentController::class, 'destroy']);
        Route::post('/segments/{segment}/recompute', [MarketingSegmentController::class, 'recompute']);
    });

    // Insurance v2 — Admin claim status machine + claims list
    Route::prefix('admin/insurance-v2')->middleware('api_scope:admin:write,admin:everything')->group(function () {
        Route::patch('/claims/{claim}/status', [InsuranceAdminController::class, 'updateClaimStatus']);
        Route::get('/claims', [InsuranceAdminController::class, 'claims']);
    });

    /*
     * Console d'administration mobile.
     *
     * Ces deux points d'entrée ne portent aucune donnée métier : l'un sert le registre de
     * couverture, l'autre des compteurs. Ils sont gardés en lecture comme le reste du groupe.
     */
    Route::prefix('admin')->middleware('api_scope:admin:read,admin:everything')->group(function () {
        Route::get('/catalog', AdminCatalogController::class);
        Route::get('/overview', AdminOverviewController::class);
    });

    /*
     * Moteur de console — un jeu d'endpoints pour tous les domaines décrits par un descripteur.
     *
     * Les écritures sont sous `admin:critical` : ces routes servent indifféremment la création
     * d'un utilisateur et la suppression d'une ligne comptable, et le descripteur ne peut pas
     * abaisser le niveau requis. La lecture reste sous `admin:read`.
     */
    /*
     * La descente géographique du catalogue, pour l'application mobile.
     *
     * Hors du moteur de console : l'état servi ici — « ce métier est-il ouvert dans cette zone » —
     * est le COUPLE (métier, zone), et un métier sans ligne est fermé plutôt qu'absent. Une liste
     * générique de `trade_zone_pricing` tairait tous les métiers pas encore réglés, c'est-à-dire
     * exactement ceux qu'on vient ouvrir.
     */
    Route::prefix('admin/catalogue')->group(function () {
        Route::middleware('api_scope:admin:read,admin:everything')->group(function () {
            Route::get('/zones/{zone}/trades', [ZoneCatalogController::class, 'trades']);
            Route::get('/trades/{trade}/journey', [JourneyBuilderController::class, 'show']);
        });

        Route::middleware('api_scope:admin:critical,admin:everything')->group(function () {
            Route::post('/zones/{zone}/trades/{trade}/toggle', [ZoneCatalogController::class, 'toggle']);
            // L'immédiat se décide zone par zone, comme le prix — et sur la même ligne.
            Route::post('/zones/{zone}/trades/{trade}/toggle-asap', [ZoneCatalogController::class, 'toggleAsap']);

            /*
             * Le constructeur de parcours. Il ne réimplémente aucune règle : la validation vient de
             * QuestionnaireValidator, la publication de TradeFormPublisher, l'ordre de
             * CatalogOrdering — les mêmes services que le web.
             */
            Route::post('/trades/{trade}/questions', [JourneyBuilderController::class, 'storeQuestion']);
            Route::patch('/questions/{question}', [JourneyBuilderController::class, 'updateQuestion']);
            Route::post('/questions/{question}/move', [JourneyBuilderController::class, 'moveQuestion']);
            Route::delete('/questions/{question}', [JourneyBuilderController::class, 'destroyQuestion']);

            Route::post('/questions/{question}/options', [JourneyBuilderController::class, 'storeOption']);
            Route::patch('/options/{option}', [JourneyBuilderController::class, 'updateOption']);
            Route::delete('/options/{option}', [JourneyBuilderController::class, 'destroyOption']);

            Route::post('/trades/{trade}/publish', [JourneyBuilderController::class, 'publish']);
        });
    });

    Route::prefix('admin/console')->group(function () {
        Route::middleware('api_scope:admin:read,admin:everything')->group(function () {
            // AVANT la route générique `/{resource}` : sans cela, « reports » serait pris pour
            // une ressource et rendrait 404 sur un module qui existe.
            Route::get('/reports/{report}', ReportController::class);

            Route::get('/{resource}', [ResourceController::class, 'index']);
            Route::get('/{resource}/{id}', [ResourceController::class, 'show']);
        });

        Route::middleware('api_scope:admin:critical,admin:everything')->group(function () {
            Route::post('/{resource}', [ResourceController::class, 'store']);
            Route::patch('/{resource}/{id}', [ResourceController::class, 'update']);
            Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy']);
            // AVANT la route par ligne : sans cela, « actions » serait pris pour un
            // identifiant et l'action globale rendrait 404 sur une ligne introuvable.
            Route::post('/{resource}/actions/{action}', [ResourceController::class, 'globalAction']);
            Route::post('/{resource}/{id}/actions/{action}', [ResourceController::class, 'action']);
        });
    });
});
