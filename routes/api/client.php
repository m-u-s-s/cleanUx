<?php

use App\Http\Controllers\Api\Client\AiQuoteController;
use App\Http\Controllers\Api\Client\BookingEstimateController;
use App\Http\Controllers\Api\Client\BookingFavoriteController;
use App\Http\Controllers\Api\Client\BookingPaymentController;
use App\Http\Controllers\Api\Client\ClientBookingController;
use App\Http\Controllers\Api\Client\ClientProfileController;
use App\Http\Controllers\Api\Client\CompanyController as ClientCompanyController;
use App\Http\Controllers\Api\Client\CompanyDirectoryController;
use App\Http\Controllers\Api\Client\DeviceTokenController;
use App\Http\Controllers\Api\Client\DisputeController;
use App\Http\Controllers\Api\Client\GdprController;
use App\Http\Controllers\Api\Client\InsuranceController;
use App\Http\Controllers\Api\Client\InvoiceApiController;
use App\Http\Controllers\Api\Client\LoyaltyController;
use App\Http\Controllers\Api\Client\LoyaltyRedemptionController;
use App\Http\Controllers\Api\Client\MarketingPreferencesController;
use App\Http\Controllers\Api\Client\NotificationPreferenceController;
use App\Http\Controllers\Api\Client\NpsController;
use App\Http\Controllers\Api\Client\PaymentMethodController;
use App\Http\Controllers\Api\Client\PromoCodeController;
use App\Http\Controllers\Api\Client\QualityInspectionClientController;
use App\Http\Controllers\Api\Client\RatingController;
use App\Http\Controllers\Api\Client\ReferralController;
use App\Http\Controllers\Api\Client\ReferralV2Controller;
use App\Http\Controllers\Api\Client\TipController;
use App\Http\Controllers\Api\Client\TripTrackingController;
use App\Http\Controllers\Api\Client\UserSafetyController;
use App\Http\Controllers\Api\ParityMapController;
use App\Http\Controllers\Api\PhoneVerificationController;
use App\Models\Trade;
use App\Services\Booking\TradeFormRenderer;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — Client endpoints
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->prefix('client')->group(function () {

    // SP3 — Annuaire des sociétés prestataires éligibles (browse mobile + web)
    Route::get('/companies', CompanyDirectoryController::class);

    // Bookings
    Route::post('/bookings/estimate', [BookingEstimateController::class, 'estimate']);
    Route::get('/bookings', [ClientBookingController::class, 'index']);
    Route::post('/bookings', [ClientBookingController::class, 'store']);
    Route::get('/bookings/{booking}', [ClientBookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [ClientBookingController::class, 'cancel']);
    Route::get('/bookings/{booking}/eta', [ClientBookingController::class, 'eta']);
    // E1 — client confirms mission start/end by scanning the on-site verification code.
    Route::post('/bookings/{booking}/qr-start', [ClientBookingController::class, 'qrStart']);
    Route::post('/bookings/{booking}/qr-end', [ClientBookingController::class, 'qrEnd']);

    // Client Invoices — list with scope isolation
    Route::get('/invoices/summary', [InvoiceApiController::class, 'summary'])->name('api.client.invoices.summary');
    Route::get('/invoices', [InvoiceApiController::class, 'index'])->name('api.client.invoices.index');
    Route::get('/invoices/{id}', [InvoiceApiController::class, 'show'])->whereNumber('id')->name('api.client.invoices.show');
    Route::get('/invoices/{id}/pdf', [InvoiceApiController::class, 'download'])->whereNumber('id')->name('api.client.invoices.pdf');

    // Phase Promotions — Codes promo + parrainage
    Route::post('/promo-codes/validate', [PromoCodeController::class, 'validate_'])->middleware('throttle:promo');
    Route::get('/referrals/me', [ReferralController::class, 'me']);
    Route::get('/referrals', [ReferralController::class, 'list']);
    Route::post('/referrals/invite', [ReferralController::class, 'invite'])->middleware('throttle:promo');

    // Referral V2 — Viral sharing + tier progress (consumes config/referral.php)
    Route::get('/referral/my-code', [ReferralV2Controller::class, 'myCode']);
    Route::get('/referral/stats', [ReferralV2Controller::class, 'stats']);
    Route::post('/referral/share', [ReferralV2Controller::class, 'share'])->middleware('throttle:promo');

    // Phase Ratings — Avis client → provider + signalement
    Route::post('/bookings/{booking}/rating', [RatingController::class, 'submit']);
    Route::post('/ratings/{feedback}/report', [RatingController::class, 'report']);

    // Phase Disputes v2 — Réclamations client
    Route::get('/disputes', [DisputeController::class, 'index']);
    Route::post('/disputes', [DisputeController::class, 'store']);
    Route::get('/disputes/{dispute}', [DisputeController::class, 'show']);
    Route::post('/disputes/{dispute}/messages', [DisputeController::class, 'message']);

    // Phase GDPR v2 — Self-service RGPD
    Route::get('/gdpr/requests', [GdprController::class, 'index']);
    Route::post('/gdpr/requests/export', [GdprController::class, 'requestExport']);
    Route::post('/gdpr/requests/erasure', [GdprController::class, 'requestErasure']);
    Route::post('/gdpr/requests/{gdprRequest}/cancel', [GdprController::class, 'cancelErasure']);

    // Phase Loyalty v2 — Programme fidélité
    Route::get('/loyalty/me', [LoyaltyController::class, 'me']);
    Route::get('/loyalty/transactions', [LoyaltyController::class, 'transactions']);

    // Loyalty Redemption Marketplace
    Route::get('/loyalty/rewards', [LoyaltyRedemptionController::class, 'catalogue']);
    Route::post('/loyalty/rewards/redeem', [LoyaltyRedemptionController::class, 'redeem']);
    Route::get('/loyalty/redemptions', [LoyaltyRedemptionController::class, 'mine']);

    // Tips v2 — pourboires post-mission
    Route::get('/bookings/{booking}/tip/suggestions', [TipController::class, 'suggestions']);
    Route::post('/bookings/{booking}/tip', [TipController::class, 'create']);
    Route::get('/tips/mine', [TipController::class, 'mine']);

    // Trip Tracking v2 — vue client (poll position provider en mission)
    Route::get('/bookings/{booking}/tracking', [TripTrackingController::class, 'currentForBooking']);
    Route::get('/bookings/{booking}/tracking/trail', [TripTrackingController::class, 'trail']);
    // Confirmation de présence : le client affiche, le prestataire scanne. POST car chaque
    // appel forge un code neuf et périme le précédent.
    Route::post('/bookings/{booking}/presence-code', [TripTrackingController::class, 'issuePresenceCode']);
    // Clôture : même direction que la présence — le client atteste, le prestataire scanne.
    Route::post('/bookings/{booking}/completion-code', [TripTrackingController::class, 'issueCompletionCode']);

    // Booking favorites — rebook 1-click
    Route::get('/favorites', [BookingFavoriteController::class, 'index']);
    Route::post('/bookings/{booking}/favorite', [BookingFavoriteController::class, 'create']);
    Route::post('/favorites/{favorite}/use', [BookingFavoriteController::class, 'markUsed']);
    Route::delete('/favorites/{favorite}', [BookingFavoriteController::class, 'destroy']);

    // Trust & Safety — Block / Report user
    Route::post('/users/{user}/block', [UserSafetyController::class, 'block']);
    Route::delete('/users/{user}/block', [UserSafetyController::class, 'unblock']);
    Route::post('/users/{user}/report', [UserSafetyController::class, 'report'])->middleware('throttle:promo');

    // NPS surveys
    Route::post('/nps/responses', [NpsController::class, 'submit']);
    Route::get('/nps/responses/mine', [NpsController::class, 'mine']);

    // AI quote estimation from photo (Claude Vision) — rate-limited
    Route::post('/ai-quote/photo', [AiQuoteController::class, 'estimateFromPhoto'])
        ->middleware('throttle:promo');

    // Phase SMS v2 — Vérification téléphone (OTP)
    Route::post('/phone/verify-request', [PhoneVerificationController::class, 'requestCode'])->middleware('throttle:otp');
    Route::post('/phone/verify-confirm', [PhoneVerificationController::class, 'confirm'])->middleware('throttle:otp');

    // Phase Marketing v2 — Préférences opt-in/opt-out (RGPD)
    Route::get('/marketing/preferences', [MarketingPreferencesController::class, 'show']);
    Route::post('/marketing/opt-out', [MarketingPreferencesController::class, 'optOut']);
    Route::post('/marketing/opt-in', [MarketingPreferencesController::class, 'optIn']);

    // Phase Notifications Preferences Center v2 — Matrice unifiée channel × category
    Route::get('/notifications/preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('/notifications/preferences', [NotificationPreferenceController::class, 'update']);
    Route::put('/notifications/preferences/bulk', [NotificationPreferenceController::class, 'bulk']);
    Route::get('/notifications/preferences/audit', [NotificationPreferenceController::class, 'audit']);

    // Phase Quality v2 — Inspection validation/dispute par client
    Route::get('/inspections/{inspection}', [QualityInspectionClientController::class, 'show']);
    Route::post('/inspections/{inspection}/validate', [QualityInspectionClientController::class, 'validate_']);
    Route::post('/inspections/{inspection}/dispute', [QualityInspectionClientController::class, 'dispute']);

    // Phase Insurance v2 — Plans + purchase + claims
    Route::get('/bookings/{booking}/insurance-plans', [InsuranceController::class, 'plansForBooking']);
    Route::post('/bookings/{booking}/insurance', [InsuranceController::class, 'purchase']);
    Route::get('/insurances', [InsuranceController::class, 'index']);
    Route::post('/insurances/{insurance}/cancel', [InsuranceController::class, 'cancel']);
    Route::get('/insurances/{insurance}/claims', [InsuranceController::class, 'listClaims']);
    Route::post('/insurances/{insurance}/claims', [InsuranceController::class, 'fileClaim']);

    // Insurance claims list (from skeletal modules section)
    Route::get('/insurance/claims', [ClientProfileController::class, 'claimsMine']);

    // Phase Push v2 — Device tokens (FCM/APNs) + préférences opt-in
    Route::get('/devices', [DeviceTokenController::class, 'index']);
    Route::post('/devices/register', [DeviceTokenController::class, 'register']);
    Route::post('/devices/unregister', [DeviceTokenController::class, 'unregister']);
    Route::patch('/devices/{deviceToken}/preferences', [DeviceTokenController::class, 'updatePreferences']);

    // Sprint 0-bis — Payment API (consumed by mobile RN app)
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/setup-intent', [PaymentMethodController::class, 'setupIntent']);
        Route::delete('/{paymentMethodId}', [PaymentMethodController::class, 'destroy']);
    });

    Route::post('/bookings/{booking}/payment-intent', [BookingPaymentController::class, 'createPaymentIntent']);

    // Mobile app — profile self-update (name, phone, locale)
    Route::put('/profile', [ClientProfileController::class, 'update']);

    // Mobile app — avatar upload
    Route::post('/profile/avatar', [ClientProfileController::class, 'uploadAvatar']);

    // Mobile app — NPS simplified endpoint (score only, no survey_code required)
    Route::post('/nps', [ClientProfileController::class, 'npsSimplified']);

    // F1 removal — the legacy client cancellation routes (cancel-with-fee / cancellation-quote)
    // were removed; clients use the unified V2 endpoints under /api/v2/client/bookings/*.
    Route::prefix('bookings')->group(function () {
        // Commission preview — lets client see platform fee before confirming
        Route::get('/{booking}/commission', [ClientProfileController::class, 'commissionPreview']);
    });

    // Multi-trade booking flow — trade form fields + services per trade (authenticated)
    // GET /api/client/trades/{trade}/form-fields
    Route::get('/trades/{trade}/form-fields', function (Trade $trade) {
        return response()->json([
            'trade' => [
                'id' => $trade->id,
                'name' => $trade->name,
                'slug' => $trade->slug,
            ],
            'fields' => app(TradeFormRenderer::class)->fieldsForTrade($trade),
            'billing_unit' => $trade->billing_unit ?? 'hourly',
            'requires_site_visit' => (bool) ($trade->requires_site_visit ?? false),
        ]);
    });

    // GET /api/client/trades/{trade}/services
    Route::get('/trades/{trade}/services', function (Trade $trade) {
        $services = $trade->services()
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'description', 'base_price', 'billing_unit', 'is_featured', 'icon'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'description' => $s->description,
                'base_price' => $s->base_price ? (float) $s->base_price : null,
                'billing_unit' => $s->billing_unit,
                'is_featured' => (bool) ($s->is_featured ?? false),
                'icon' => $s->icon,
            ]);

        return response()->json(['data' => $services]);
    });
});

// ─────────────────────────────────────────────
// Parity map — shared across all authenticated roles
// GET /api/parity-map  (no /client prefix — intentional)
// ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->get('/parity-map', ParityMapController::class)->name('api.parity-map');

/*
|--------------------------------------------------------------------------
| API — Espace société cliente
|--------------------------------------------------------------------------
|
| GROUPE SÉPARÉ, sous `auth:sanctum` seul. Comme côté prestataire, la garde est portée par le
| contrôleur : organisation active obligatoire, puis une permission par écriture. Voir
| `Api\Client\CompanyController`.
|
| Les services métier de la phase 1 sont réutilisés tels quels (MultiSiteRequestService,
| SigningAppointmentService) : le web et le mobile doivent appliquer les mêmes règles.
*/
Route::middleware('auth:sanctum')->prefix('client/company')->group(function () {
    Route::get('/sites', [ClientCompanyController::class, 'sites']);
    Route::get('/members', [ClientCompanyController::class, 'members']);

    Route::post('/multi-site-request', [ClientCompanyController::class, 'multiSiteRequest']);

    Route::get('/signing-appointments', [ClientCompanyController::class, 'signingAppointments']);
    Route::post('/signing-appointments', [ClientCompanyController::class, 'createSigningAppointment']);
});
