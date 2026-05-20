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
// Public — Provider profiles + ratings (no auth)
// ─────────────────────────────────────────────

Route::get('/providers/{provider}',         [\App\Http\Controllers\Api\Public\ProviderProfileController::class, 'show']);
Route::get('/providers/{provider}/ratings', [\App\Http\Controllers\Api\Public\ProviderProfileController::class, 'ratings']);

// Phase i18n v2 — Liste des locales supportées (public, pour pickers UI)
Route::get('/locales', [\App\Http\Controllers\Api\LocaleListController::class, 'index']);

// Phase Search v2 — Recherche publique providers/services/adresses
Route::get('/search/providers',           [\App\Http\Controllers\Api\Public\SearchController::class, 'providers']);
Route::get('/search/services',            [\App\Http\Controllers\Api\Public\SearchController::class, 'services']);
Route::get('/search/postal-autocomplete', [\App\Http\Controllers\Api\Public\SearchController::class, 'postalAutocomplete']);

// Phase Analytics v2 — Ingestion publique (auth optionnelle via header bearer)
Route::post('/analytics/track', [\App\Http\Controllers\Api\AnalyticsController::class, 'track']);
Route::post('/analytics/page',  [\App\Http\Controllers\Api\AnalyticsController::class, 'page']);

// Phase FX v2 — Devises supportées + taux + conversion (public)
Route::get('/fx/currencies', [\App\Http\Controllers\Api\Public\FxController::class, 'currencies']);
Route::get('/fx/rates',      [\App\Http\Controllers\Api\Public\FxController::class, 'rates']);
Route::post('/fx/convert',   [\App\Http\Controllers\Api\Public\FxController::class, 'convert']);

// Phase Pricing v2 — Service catalog + quote engine (public read + preview)
Route::get('/v2/pricing/services', [\App\Http\Controllers\Api\PricingV2Controller::class, 'services']);
Route::post('/v2/pricing/preview', [\App\Http\Controllers\Api\PricingV2Controller::class, 'preview']);
Route::post('/v2/pricing/quote',   [\App\Http\Controllers\Api\PricingV2Controller::class, 'quote']);

// Phase GDPR v2 — Download d'export via URL signée
Route::middleware(['signed', 'auth:sanctum'])
    ->get('/client/gdpr/requests/{gdprRequest}/download',
        [\App\Http\Controllers\Api\Client\GdprController::class, 'downloadExport'])
    ->name('api.gdpr.download');

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

    // Phase Analytics v2 — Identifier (link anonymous_id → user_id)
    Route::post('/analytics/identify', [\App\Http\Controllers\Api\AnalyticsController::class, 'identify']);

    // ─────────────────────────────────────────
    // Client endpoints
    // ─────────────────────────────────────────

    Route::prefix('client')->group(function () {
        Route::get('/bookings',                   [ClientBookingController::class, 'index']);
        Route::post('/bookings',                  [ClientBookingController::class, 'store']);
        Route::get('/bookings/{booking}',         [ClientBookingController::class, 'show']);
        Route::post('/bookings/{booking}/cancel', [ClientBookingController::class, 'cancel']);
        Route::get('/bookings/{booking}/eta',     [ClientBookingController::class, 'eta']);

        // Phase Promotions — Codes promo + parrainage
        Route::post('/promo-codes/validate', [\App\Http\Controllers\Api\Client\PromoCodeController::class, 'validate_'])->middleware('throttle:promo');
        Route::get('/referrals/me',          [\App\Http\Controllers\Api\Client\ReferralController::class, 'me']);
        Route::get('/referrals',             [\App\Http\Controllers\Api\Client\ReferralController::class, 'list']);
        Route::post('/referrals/invite',     [\App\Http\Controllers\Api\Client\ReferralController::class, 'invite'])->middleware('throttle:promo');

        // Phase Ratings — Avis client → provider + signalement
        Route::post('/bookings/{booking}/rating', [\App\Http\Controllers\Api\Client\RatingController::class, 'submit']);
        Route::post('/ratings/{feedback}/report', [\App\Http\Controllers\Api\Client\RatingController::class, 'report']);

        // Phase Disputes v2 — Réclamations client
        Route::get('/disputes',                       [\App\Http\Controllers\Api\Client\DisputeController::class, 'index']);
        Route::post('/disputes',                      [\App\Http\Controllers\Api\Client\DisputeController::class, 'store']);
        Route::get('/disputes/{dispute}',             [\App\Http\Controllers\Api\Client\DisputeController::class, 'show']);
        Route::post('/disputes/{dispute}/messages',   [\App\Http\Controllers\Api\Client\DisputeController::class, 'message']);

        // Phase GDPR v2 — Self-service RGPD
        Route::get('/gdpr/requests',                            [\App\Http\Controllers\Api\Client\GdprController::class, 'index']);
        Route::post('/gdpr/requests/export',                    [\App\Http\Controllers\Api\Client\GdprController::class, 'requestExport']);
        Route::post('/gdpr/requests/erasure',                   [\App\Http\Controllers\Api\Client\GdprController::class, 'requestErasure']);
        Route::post('/gdpr/requests/{gdprRequest}/cancel',      [\App\Http\Controllers\Api\Client\GdprController::class, 'cancelErasure']);

        // Phase Loyalty v2 — Programme fidélité
        Route::get('/loyalty/me',                               [\App\Http\Controllers\Api\Client\LoyaltyController::class, 'me']);
        Route::get('/loyalty/transactions',                     [\App\Http\Controllers\Api\Client\LoyaltyController::class, 'transactions']);

        // Loyalty Redemption Marketplace
        Route::get('/loyalty/rewards',                          [\App\Http\Controllers\Api\Client\LoyaltyRedemptionController::class, 'catalogue']);
        Route::post('/loyalty/rewards/redeem',                  [\App\Http\Controllers\Api\Client\LoyaltyRedemptionController::class, 'redeem']);
        Route::get('/loyalty/redemptions',                      [\App\Http\Controllers\Api\Client\LoyaltyRedemptionController::class, 'mine']);

        // Tips v2 — pourboires post-mission
        Route::get('/bookings/{booking}/tip/suggestions',       [\App\Http\Controllers\Api\Client\TipController::class, 'suggestions']);
        Route::post('/bookings/{booking}/tip',                  [\App\Http\Controllers\Api\Client\TipController::class, 'create']);
        Route::get('/tips/mine',                                [\App\Http\Controllers\Api\Client\TipController::class, 'mine']);

        // Trip Tracking v2 — vue client (poll position provider en mission)
        Route::get('/bookings/{booking}/tracking',              [\App\Http\Controllers\Api\Client\TripTrackingController::class, 'currentForBooking']);
        Route::get('/bookings/{booking}/tracking/trail',        [\App\Http\Controllers\Api\Client\TripTrackingController::class, 'trail']);

        // Booking favorites — rebook 1-click
        Route::get('/favorites',                                [\App\Http\Controllers\Api\Client\BookingFavoriteController::class, 'index']);
        Route::post('/bookings/{booking}/favorite',             [\App\Http\Controllers\Api\Client\BookingFavoriteController::class, 'create']);
        Route::post('/favorites/{favorite}/use',                [\App\Http\Controllers\Api\Client\BookingFavoriteController::class, 'markUsed']);
        Route::delete('/favorites/{favorite}',                  [\App\Http\Controllers\Api\Client\BookingFavoriteController::class, 'destroy']);

        // Trust & Safety — Block / Report user
        Route::post('/users/{user}/block',     [\App\Http\Controllers\Api\Client\UserSafetyController::class, 'block']);
        Route::delete('/users/{user}/block',   [\App\Http\Controllers\Api\Client\UserSafetyController::class, 'unblock']);
        Route::post('/users/{user}/report',    [\App\Http\Controllers\Api\Client\UserSafetyController::class, 'report'])->middleware('throttle:promo');

        // Phase SMS v2 — Vérification téléphone (OTP)
        Route::post('/phone/verify-request', [\App\Http\Controllers\Api\Client\PhoneVerificationController::class, 'requestCode'])->middleware('throttle:otp');
        Route::post('/phone/verify-confirm', [\App\Http\Controllers\Api\Client\PhoneVerificationController::class, 'confirm'])->middleware('throttle:otp');

        // Phase Marketing v2 — Préférences opt-in/opt-out (RGPD)
        Route::get('/marketing/preferences', [\App\Http\Controllers\Api\Client\MarketingPreferencesController::class, 'show']);
        Route::post('/marketing/opt-out',    [\App\Http\Controllers\Api\Client\MarketingPreferencesController::class, 'optOut']);
        Route::post('/marketing/opt-in',     [\App\Http\Controllers\Api\Client\MarketingPreferencesController::class, 'optIn']);

        // Phase Notifications Preferences Center v2 — Matrice unifiée channel × category
        Route::get('/notifications/preferences',       [\App\Http\Controllers\Api\Client\NotificationPreferenceController::class, 'show']);
        Route::put('/notifications/preferences',       [\App\Http\Controllers\Api\Client\NotificationPreferenceController::class, 'update']);
        Route::put('/notifications/preferences/bulk',  [\App\Http\Controllers\Api\Client\NotificationPreferenceController::class, 'bulk']);
        Route::get('/notifications/preferences/audit', [\App\Http\Controllers\Api\Client\NotificationPreferenceController::class, 'audit']);

        // Phase Quality v2 — Inspection validation/dispute par client
        Route::get('/inspections/{inspection}',           [\App\Http\Controllers\Api\Client\QualityInspectionClientController::class, 'show']);
        Route::post('/inspections/{inspection}/validate', [\App\Http\Controllers\Api\Client\QualityInspectionClientController::class, 'validate_']);
        Route::post('/inspections/{inspection}/dispute',  [\App\Http\Controllers\Api\Client\QualityInspectionClientController::class, 'dispute']);

        // Phase Insurance v2 — Plans + purchase + claims
        Route::get('/bookings/{booking}/insurance-plans',  [\App\Http\Controllers\Api\Client\InsuranceController::class, 'plansForBooking']);
        Route::post('/bookings/{booking}/insurance',       [\App\Http\Controllers\Api\Client\InsuranceController::class, 'purchase']);
        Route::get('/insurances',                          [\App\Http\Controllers\Api\Client\InsuranceController::class, 'index']);
        Route::post('/insurances/{insurance}/cancel',      [\App\Http\Controllers\Api\Client\InsuranceController::class, 'cancel']);
        Route::get('/insurances/{insurance}/claims',       [\App\Http\Controllers\Api\Client\InsuranceController::class, 'listClaims']);
        Route::post('/insurances/{insurance}/claims',      [\App\Http\Controllers\Api\Client\InsuranceController::class, 'fileClaim']);

        // Phase Push v2 — Device tokens (FCM/APNs) + préférences opt-in
        Route::get('/devices',                                 [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'index']);
        Route::post('/devices/register',                       [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'register']);
        Route::post('/devices/unregister',                     [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'unregister']);
        Route::patch('/devices/{deviceToken}/preferences',     [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'updatePreferences']);
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

    // Phase Ratings — Avis provider → client + réponse aux avis reçus
    Route::prefix('provider')->group(function () {
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
    });

    // Phase Matching v2 — Simulation admin
    Route::prefix('admin/matching')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/bookings/{booking}/simulate',   [\App\Http\Controllers\Api\Admin\MatchingSimulationController::class, 'simulate']);
    });

    // Phase Risk v2 — Évaluations + holds + review (admin)
    Route::prefix('admin/risk')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/evaluations',               [\App\Http\Controllers\Api\Admin\RiskController::class, 'evaluations']);
        Route::get('/holds',                     [\App\Http\Controllers\Api\Admin\RiskController::class, 'holds']);
        Route::post('/holds/{hold}/review',      [\App\Http\Controllers\Api\Admin\RiskController::class, 'reviewHold']);
    });

    // Phase Onboarding v2 — Journeys orchestration (client/provider/enterprise)
    Route::prefix('v2/onboarding')->group(function () {
        Route::get('/me',                        [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'me']);
        Route::post('/steps/{step}/complete',    [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'completeStep']);
        Route::post('/steps/{step}/skip',        [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'skipStep']);
    });
    Route::prefix('admin/onboarding-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/progress',                  [\App\Http\Controllers\Api\OnboardingV2Controller::class, 'adminIndex']);
    });

    // Phase Pricing v2 — Admin quotes listing
    Route::prefix('admin/pricing-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/quotes',                    [\App\Http\Controllers\Api\PricingV2Controller::class, 'adminQuotes']);
    });

    // Phase Contracts v2 — Templates + documents + signatures
    Route::prefix('v2/contracts')->group(function () {
        Route::get('/templates',                        [\App\Http\Controllers\Api\ContractsV2Controller::class, 'activeTemplates']);
        Route::post('/documents',                       [\App\Http\Controllers\Api\ContractsV2Controller::class, 'renderDocument']);
        Route::get('/documents/{document}',             [\App\Http\Controllers\Api\ContractsV2Controller::class, 'showDocument']);
        Route::post('/documents/{document}/sign',       [\App\Http\Controllers\Api\ContractsV2Controller::class, 'signDocument']);
        Route::get('/documents/{document}/pdf',         [\App\Http\Controllers\Api\ContractsV2Controller::class, 'downloadPdf']);
    });
    Route::prefix('admin/contracts-v2')->middleware('api_scope:read:contracts,admin:everything')->group(function () {
        Route::get('/templates',                        [\App\Http\Controllers\Api\ContractsV2Controller::class, 'adminTemplates']);
        Route::get('/documents',                        [\App\Http\Controllers\Api\ContractsV2Controller::class, 'adminDocuments']);
        Route::post('/signatures/{signature}/invalidate', [\App\Http\Controllers\Api\ContractsV2Controller::class, 'adminInvalidateSignature']);
    });

    // Phase Fleet v2 — Vehicles / Equipment / Assignments / Maintenance / Certifications
    Route::prefix('v2/fleet')->group(function () {
        Route::get('/me/assignments',                          [\App\Http\Controllers\Api\FleetV2Controller::class, 'listMyAssignments']);
        Route::post('/assignments/{assignment}/return',        [\App\Http\Controllers\Api\FleetV2Controller::class, 'returnAssignment']);
        Route::get('/available',                               [\App\Http\Controllers\Api\FleetV2Controller::class, 'findAvailable']);
    });
    Route::prefix('admin/fleet-v2')->middleware('api_scope:admin:everything')->group(function () {
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

    // Phase KYB v2 — Compliance entreprises
    Route::prefix('v2/kyb')->group(function () {
        Route::get('/me/entities',                              [\App\Http\Controllers\Api\KybV2Controller::class, 'listMyEntities']);
        Route::post('/me/entities',                             [\App\Http\Controllers\Api\KybV2Controller::class, 'startVerification']);
        Route::get('/me/entities/{entity}',                     [\App\Http\Controllers\Api\KybV2Controller::class, 'showMyEntity']);
        Route::post('/me/entities/{entity}/documents',          [\App\Http\Controllers\Api\KybV2Controller::class, 'uploadDocument']);
        Route::get('/documents/{document}/download',            [\App\Http\Controllers\Api\KybV2Controller::class, 'downloadDocument']);
    });
    Route::prefix('admin/kyb-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/entities',                                 [\App\Http\Controllers\Api\KybV2Controller::class, 'adminListEntities']);
        Route::post('/entities/{entity}/run-verifications',     [\App\Http\Controllers\Api\KybV2Controller::class, 'adminRunVerifications']);
        Route::post('/entities/{entity}/run-sanctions',         [\App\Http\Controllers\Api\KybV2Controller::class, 'adminRunSanctions']);
        Route::post('/entities/{entity}/approve',               [\App\Http\Controllers\Api\KybV2Controller::class, 'adminApprove']);
        Route::post('/entities/{entity}/reject',                [\App\Http\Controllers\Api\KybV2Controller::class, 'adminReject']);
        Route::post('/entities/{entity}/beneficial-owners',     [\App\Http\Controllers\Api\KybV2Controller::class, 'adminAddBeneficialOwner']);
        Route::get('/documents',                                [\App\Http\Controllers\Api\KybV2Controller::class, 'adminListDocuments']);
        Route::post('/documents/{document}/review',             [\App\Http\Controllers\Api\KybV2Controller::class, 'adminReviewDocument']);
    });

    // Phase Tenancy v2 — Multi-tenancy / White-label
    Route::prefix('v2/tenancy')->group(function () {
        Route::get('/me',                                       [\App\Http\Controllers\Api\TenancyV2Controller::class, 'currentTenant']);
    });
    Route::prefix('admin/tenancy-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/tenants',                                  [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminListTenants']);
        Route::post('/tenants',                                 [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminCreateTenant']);
        Route::post('/tenants/{tenant}/activate',               [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminActivateTenant']);
        Route::post('/tenants/{tenant}/suspend',                [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminSuspendTenant']);
        Route::post('/tenants/{tenant}/archive',                [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminArchiveTenant']);
        Route::post('/tenants/{tenant}/change-plan',            [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminChangePlan']);
        Route::post('/tenants/{tenant}/theming',                [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminUpdateTheming']);
        Route::get('/tenants/{tenant}/domains',                 [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminListDomains']);
        Route::post('/tenants/{tenant}/domains',                [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminAddDomain']);
        Route::post('/domains/{domain}/verify',                 [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminVerifyDomain']);
        Route::get('/tenants/{tenant}/users',                   [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminListUsers']);
        Route::post('/tenants/{tenant}/users',                  [\App\Http\Controllers\Api\TenancyV2Controller::class, 'adminAttachUser']);
    });

    // Phase Accounting v2 — Ledger comptable + exports compta
    // Scope api_scope:admin:everything (compta admin uniquement)
    Route::prefix('admin/accounting-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/entries',                              [\App\Http\Controllers\Api\AccountingV2Controller::class, 'listEntries']);
        Route::post('/entries',                             [\App\Http\Controllers\Api\AccountingV2Controller::class, 'postEntries']);
        Route::get('/account-balance',                      [\App\Http\Controllers\Api\AccountingV2Controller::class, 'accountBalance']);
        Route::get('/periods',                              [\App\Http\Controllers\Api\AccountingV2Controller::class, 'listPeriods']);
        Route::post('/periods/{year}/{month}/close',        [\App\Http\Controllers\Api\AccountingV2Controller::class, 'closePeriod']);
        Route::post('/periods/{period}/reopen',             [\App\Http\Controllers\Api\AccountingV2Controller::class, 'reopenPeriod']);
        Route::get('/exports',                              [\App\Http\Controllers\Api\AccountingV2Controller::class, 'listExports']);
        Route::post('/exports',                             [\App\Http\Controllers\Api\AccountingV2Controller::class, 'generateExport']);
        Route::get('/exports/{export}/download',            [\App\Http\Controllers\Api\AccountingV2Controller::class, 'downloadExport']);
    });

    // Phase Subscriptions v2 — Recurring billing (plans/cycles/invoices)
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
    Route::prefix('admin/subscriptions-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/subscriptions',                        [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminListSubscriptions']);
        Route::get('/cycles',                               [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminListCycles']);
        Route::post('/cycles/{cycle}/retry-billing',        [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminRetryBilling']);
        Route::post('/subscriptions/{subscription}/force-cancel', [\App\Http\Controllers\Api\SubscriptionsV2Controller::class, 'adminForceCancel']);
    });

    // Phase Chat v2 — In-app messaging (booking/dispute/admin)
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
    Route::prefix('admin/chat-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/threads',                              [\App\Http\Controllers\Api\ChatV2Controller::class, 'adminListThreads']);
        Route::get('/flagged',                              [\App\Http\Controllers\Api\ChatV2Controller::class, 'adminListFlagged']);
        Route::post('/messages/{message}/moderate',         [\App\Http\Controllers\Api\ChatV2Controller::class, 'adminModerate']);
    });

    // Phase API Tokens v2 — Self-service tokens + scopes catalog + admin
    Route::prefix('v2/tokens')->group(function () {
        Route::get('/scopes',                  [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'scopesCatalog']);
        Route::get('/me/tokens',               [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'listMyTokens']);
        Route::post('/me/tokens',              [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'createMyToken']);
        Route::post('/me/tokens/{token}/rotate', [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'rotateMyToken']);
        Route::delete('/me/tokens/{token}',    [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'revokeMyToken']);
    });
    Route::prefix('admin/api-tokens-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/tokens',                       [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminListTokens']);
        Route::get('/usages',                       [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminListUsages']);
        Route::post('/tokens/{token}/suspend',      [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminSuspend']);
        Route::post('/tokens/{token}/unsuspend',    [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminUnsuspend']);
        Route::delete('/tokens/{token}',            [\App\Http\Controllers\Api\ApiTokensV2Controller::class, 'adminRevoke']);
    });

    // Phase Geolocation v2 — Address autocomplete + geocoding + distance
    Route::prefix('v2/geo')->group(function () {
        Route::get('/autocomplete',  [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'autocomplete']);
        Route::get('/geocode',       [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'geocode']);
        Route::get('/reverse',       [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'reverse']);
        Route::post('/distance',     [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'distance']);
    });
    Route::prefix('admin/geolocation-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/lookups',       [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'adminLookups']);
        Route::get('/stats',         [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'adminStats']);
        Route::post('/cache/purge',  [\App\Http\Controllers\Api\GeolocationV2Controller::class, 'adminPurgeCache']);
    });

    // Phase Webhooks v2 — Outbound B2B
    // Scope api_scope:admin:webhooks — session-auth (UI admin) bypasse, B2B tokens doivent avoir le scope.
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
    });

    // Phase Cancellation v2 — Quote + execute (client/provider) + admin override
    Route::prefix('v2/client/bookings')->group(function () {
        Route::get('/{booking}/cancellation-quote',  [\App\Http\Controllers\Api\CancellationV2Controller::class, 'clientQuote']);
        Route::post('/{booking}/cancel',             [\App\Http\Controllers\Api\CancellationV2Controller::class, 'clientExecute']);
    });
    Route::prefix('v2/provider/bookings')->group(function () {
        Route::get('/{booking}/cancellation-quote',  [\App\Http\Controllers\Api\CancellationV2Controller::class, 'providerQuote']);
        Route::post('/{booking}/cancel',             [\App\Http\Controllers\Api\CancellationV2Controller::class, 'providerExecute']);
    });
    Route::prefix('admin/cancellations-v2')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/',                              [\App\Http\Controllers\Api\CancellationV2Controller::class, 'adminIndex']);
        Route::post('/{cancellation}/override',      [\App\Http\Controllers\Api\CancellationV2Controller::class, 'adminOverride']);
    });

    // Phase Audit v2 — Events search / pin / export (admin)
    Route::prefix('admin/audit')->middleware('api_scope:admin:everything')->group(function () {
        Route::get('/events',                    [\App\Http\Controllers\Api\Admin\AuditController::class, 'index']);
        Route::get('/events/export',             [\App\Http\Controllers\Api\Admin\AuditController::class, 'export']);
        Route::get('/events/{event}',            [\App\Http\Controllers\Api\Admin\AuditController::class, 'show']);
        Route::post('/events/{event}/pin',       [\App\Http\Controllers\Api\Admin\AuditController::class, 'pin']);
        Route::post('/events/{event}/unpin',     [\App\Http\Controllers\Api\Admin\AuditController::class, 'unpin']);
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