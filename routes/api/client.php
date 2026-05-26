<?php

use App\Http\Controllers\Api\Client\CancellationController;
use App\Http\Controllers\Api\Client\ClientBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — Client endpoints
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->prefix('client')->group(function () {

    // Bookings
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

    // NPS surveys
    Route::post('/nps/responses',          [\App\Http\Controllers\Api\Client\NpsController::class, 'submit']);
    Route::get('/nps/responses/mine',      [\App\Http\Controllers\Api\Client\NpsController::class, 'mine']);

    // AI quote estimation from photo (Claude Vision) — rate-limited
    Route::post('/ai-quote/photo',         [\App\Http\Controllers\Api\Client\AiQuoteController::class, 'estimateFromPhoto'])
        ->middleware('throttle:promo');

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

    // Insurance claims list (from skeletal modules section)
    Route::get('/insurance/claims', [\App\Http\Controllers\Api\Client\ClientProfileController::class, 'claimsMine']);

    // Phase Push v2 — Device tokens (FCM/APNs) + préférences opt-in
    Route::get('/devices',                                 [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'index']);
    Route::post('/devices/register',                       [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'register']);
    Route::post('/devices/unregister',                     [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'unregister']);
    Route::patch('/devices/{deviceToken}/preferences',     [\App\Http\Controllers\Api\Client\DeviceTokenController::class, 'updatePreferences']);

    // Sprint 0-bis — Payment API (consumed by mobile RN app)
    Route::prefix('payment-methods')->group(function () {
        Route::get('/',                        [\App\Http\Controllers\Api\Client\PaymentMethodController::class, 'index']);
        Route::post('/setup-intent',           [\App\Http\Controllers\Api\Client\PaymentMethodController::class, 'setupIntent']);
        Route::delete('/{paymentMethodId}',    [\App\Http\Controllers\Api\Client\PaymentMethodController::class, 'destroy']);
    });

    Route::post('/bookings/{booking}/payment-intent', [\App\Http\Controllers\Api\Client\BookingPaymentController::class, 'createPaymentIntent']);

    // Mobile app — profile self-update (name, phone, locale)
    Route::put('/profile',       [\App\Http\Controllers\Api\Client\ClientProfileController::class, 'update']);

    // Mobile app — avatar upload
    Route::post('/profile/avatar', [\App\Http\Controllers\Api\Client\ClientProfileController::class, 'uploadAvatar']);

    // Mobile app — NPS simplified endpoint (score only, no survey_code required)
    Route::post('/nps', [\App\Http\Controllers\Api\Client\ClientProfileController::class, 'npsSimplified']);

    // Phase 14 — Cancellation client (legacy)
    Route::prefix('bookings')->group(function () {
        Route::get('/{booking}/cancellation-quote', [CancellationController::class, 'quote']);
        Route::post('/{booking}/cancel-with-fee',   [CancellationController::class, 'cancelWithFee']);

        // Commission preview — lets client see platform fee before confirming
        Route::get('/{booking}/commission', [\App\Http\Controllers\Api\Client\ClientProfileController::class, 'commissionPreview']);
    });
});
