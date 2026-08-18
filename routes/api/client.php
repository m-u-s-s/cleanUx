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
use App\Http\Controllers\Api\Client\GovernanceController;
use App\Http\Controllers\Api\Client\HomeInsightsController;
use App\Http\Controllers\Api\Client\InsuranceController;
use App\Http\Controllers\Api\Client\InvoiceApiController;
use App\Http\Controllers\Api\Client\LoyaltyController;
use App\Http\Controllers\Api\Client\LoyaltyRedemptionController;
use App\Http\Controllers\Api\Client\MarketingPreferencesController;
use App\Http\Controllers\Api\Client\MissionOnSiteController as ClientMissionOnSiteController;
use App\Http\Controllers\Api\Client\NotificationPreferenceController;
use App\Http\Controllers\Api\Client\NpsController;
use App\Http\Controllers\Api\Client\PaymentMethodController;
use App\Http\Controllers\Api\Client\PlaceController;
use App\Http\Controllers\Api\Client\PromoCodeController;
use App\Http\Controllers\Api\Client\QualityInspectionClientController;
use App\Http\Controllers\Api\Client\RatingController;
use App\Http\Controllers\Api\Client\ReceivedQuoteController;
use App\Http\Controllers\Api\Client\ReferralController;
use App\Http\Controllers\Api\Client\ReferralV2Controller;
use App\Http\Controllers\Api\Client\TipController;
use App\Http\Controllers\Api\Client\TripTrackingController;
use App\Http\Controllers\Api\Client\UserSafetyController;
use App\Http\Controllers\Api\MaskedCallController;
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
    /*
     * PARTAGER LE SUIVI (E3). C'est depuis le téléphone qu'on partage : le lien part par SMS
     * dans la foulée, sur le même appareil. Signé et expirant — il montre une position en
     * temps réel, et un partage qui survit à l'intervention devient un traceur.
     */
    Route::post('/bookings/{booking}/tracking/share', [TripTrackingController::class, 'share']);
    // Confirmation de présence : le client affiche, le prestataire scanne. POST car chaque
    // appel forge un code neuf et périme le précédent.
    Route::post('/bookings/{booking}/presence-code', [TripTrackingController::class, 'issuePresenceCode']);
    // Clôture : même direction que la présence — le client atteste, le prestataire scanne.
    Route::post('/bookings/{booking}/completion-code', [TripTrackingController::class, 'issueCompletionCode']);

    /*
     * LE SUIVI « SUR PLACE » CÔTÉ CLIENT.
     *
     * Le suivi existant s'arrête à la porte : il montre le trajet, puis plus rien. Ces trois
     * lectures couvrent le temps de l'intervention elle-même — ce qui est fait, ce qui a été
     * photographié, ce qui a mal tourné.
     */
    Route::get('/bookings/{booking}/onsite/timeline', [ClientMissionOnSiteController::class, 'timeline']);
    Route::get('/bookings/{booking}/onsite/media', [ClientMissionOnSiteController::class, 'media']);
    Route::get('/bookings/{booking}/onsite/incidents', [ClientMissionOnSiteController::class, 'incidents']);
    // F12 — répondre en un geste au supplément proposé sur place.
    Route::get('/bookings/{booking}/onsite/extras', [ClientMissionOnSiteController::class, 'extras']);
    // F8 — joindre le prestataire sans connaître son numéro.
    Route::get('/bookings/{booking}/masked-call', [MaskedCallController::class, 'pourLaReservation']);
    // F14 — déclarer son absence : la preuve de présence bascule sur une photo d'arrivée.
    Route::post('/bookings/{booking}/onsite/absence', [ClientMissionOnSiteController::class, 'declarerAbsence']);
    // F15 — répondre au « tout va bien ? » en un geste.
    Route::post('/bookings/{booking}/onsite/checkin', [ClientMissionOnSiteController::class, 'repondreAuPing']);
    /*
     * PROLONGER — acheter du temps en plus, au tarif normal.
     *
     * Sur le chemin `onsite` et non sur celui de la réservation : la prolongation se décide le plus
     * souvent PENDANT l'intervention, depuis l'écran de suivi, quand le client voit le compteur
     * approcher de la fin. La fenêtre se ferme à la fin de la franchise de dépassement.
     */
    Route::post('/bookings/{booking}/onsite/extend', [ClientMissionOnSiteController::class, 'prolonger']);
    /*
     * MA LISTE DE TÂCHES — ce que le client veut qu'on fasse chez lui.
     *
     * Sur le chemin `onsite` comme la prolongation, et pour la même raison : elle se décide
     * souvent PENDANT l'intervention, depuis l'écran de suivi, quand le client voit le prestataire
     * arriver et se rappelle la hotte. La fenêtre se ferme trente minutes après le démarrage.
     */
    Route::get('/bookings/{booking}/onsite/todo', [ClientMissionOnSiteController::class, 'todo']);
    Route::post('/bookings/{booking}/onsite/todo', [ClientMissionOnSiteController::class, 'ajouterUneTache']);
    Route::delete('/bookings/{booking}/onsite/todo/{item}', [ClientMissionOnSiteController::class, 'retirerUneTache']);
    /*
     * LE NOUVEAU DEVIS — le client voit les DEUX totaux et tranche lui-meme la suite.
     *
     * Le refus ne declenche pas l'annulation depuis ici : `must_cancel` la propose, et c'est le
     * parcours d'annulation, avec ses motifs exemptes, qui decide ce qu'elle coute.
     */
    Route::get('/bookings/{booking}/onsite/quote-revision', [ClientMissionOnSiteController::class, 'revisionDeDevis']);
    Route::post('/bookings/{booking}/onsite/quote-revision/{revision}/accept', [ClientMissionOnSiteController::class, 'accepterLaRevision']);
    Route::post('/bookings/{booking}/onsite/quote-revision/{revision}/decline', [ClientMissionOnSiteController::class, 'refuserLaRevision']);
    // F16 — la clôture guidée : rapport, puis pourboire, puis avis.
    Route::get('/bookings/{booking}/onsite/closure', [ClientMissionOnSiteController::class, 'closureFlow']);
    Route::post('/bookings/{booking}/onsite/extras/{extra}/approve', [ClientMissionOnSiteController::class, 'approveExtra']);
    Route::post('/bookings/{booking}/onsite/extras/{extra}/decline', [ClientMissionOnSiteController::class, 'declineExtra']);

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

/*
|--------------------------------------------------------------------------
| Espace société CLIENTE
|--------------------------------------------------------------------------
|
| Pendant exact du groupe `provider/company`, et gardé de la même façon : pas de middleware
| `role:` ni `org.type:` sur le groupe, mais une garde portée par le contrôleur.
|
| La raison est la même qu'en face, et elle est concrète : un rôle FINANCE ou REQUESTER n'est pas
| « client particulier », et les soumettre au filtre de rôle qui garde le reste de `/client/*`
| fermerait à un comptable l'accès à la facturation de sa propre société. La garde utile n'est pas
| le rôle plateforme mais l'organisation active, puis la permission — voir `Client\CompanyController`.
*/
Route::middleware('auth:sanctum')->prefix('client/company')->group(function () {
    Route::get('/overview', [ClientCompanyController::class, 'overview']);

    Route::get('/sites', [ClientCompanyController::class, 'sites']);
    Route::post('/sites', [ClientCompanyController::class, 'createSite']);

    Route::get('/bookings', [ClientCompanyController::class, 'bookings']);
    Route::get('/members', [ClientCompanyController::class, 'members']);
    Route::get('/contracts', [ClientCompanyController::class, 'contracts']);
    Route::get('/billing', [ClientCompanyController::class, 'billing']);

    /*
     * LE PILOTAGE (E7, E8, E9).
     *
     * L'APPROBATION EST MOBILE AVANT TOUT : une demande qui attend bloque une intervention, et
     * l'approbateur est rarement à son bureau. Chaque heure d'attente est une heure où le
     * prestataire n'est pas cherché — et sur une fuite d'eau, ça compte.
     *
     * Les exports comptables (E11) restent au web, délibérément : un fichier FEC destiné à un
     * logiciel de comptabilité n'a rien à faire dans le stockage d'un téléphone.
     */
    Route::get('/budgets', [GovernanceController::class, 'budgets']);
    Route::get('/service-level', [GovernanceController::class, 'serviceLevel']);
    Route::get('/approvals', [GovernanceController::class, 'approvals']);
    Route::post('/approvals/{booking}/decision', [GovernanceController::class, 'decideApproval']);
});

/*
|--------------------------------------------------------------------------
| API — Devis reçus (E24)
|--------------------------------------------------------------------------
|
| LA MOITIÉ MANQUANTE DU MODULE. Une société qui bâtit un devis et l'envoie à un client qui ne
| peut pas y répondre n'a rien envoyé : le client découvre le document par une notification
| poussée sur son téléphone — et devrait ouvrir un ordinateur pour dire oui.
|
| Aucun middleware de rôle : le devis est gardé par son DESTINATAIRE, `client_user_id`. Un
| filtre de rôle fermerait la porte à un contact d'entreprise qui n'est pas « client
| particulier », alors que c'est lui qui décide.
*/
Route::middleware('auth:sanctum')->prefix('client/quotes')->group(function () {
    Route::get('/', [ReceivedQuoteController::class, 'index']);
    Route::get('/{quote}', [ReceivedQuoteController::class, 'show']);
    // Accepter CRÉE le travail : chaque ligne porte un métier et devient une réservation.
    Route::post('/{quote}/accept', [ReceivedQuoteController::class, 'accept']);
    Route::post('/{quote}/decline', [ReceivedQuoteController::class, 'decline']);
});

/*
|--------------------------------------------------------------------------
| API — Carnet de lieux (E2)
|--------------------------------------------------------------------------
|
| C'est SUR PLACE qu'on note ce qu'il faut savoir d'un lieu : le digicode qu'on vient de
| composer, l'étage, la clé chez la voisine du deuxième. Renvoyer cette saisie à un ordinateur
| revient à ne jamais la faire.
|
| Aucun middleware de rôle : le carnet est gardé par son PROPRIÉTAIRE. Un filtre de rôle
| fermerait la porte à un contact d'entreprise, qui commande aussi pour lui-même.
*/
Route::middleware('auth:sanctum')->prefix('client/places')->group(function () {
    Route::get('/', [PlaceController::class, 'index']);
    Route::post('/', [PlaceController::class, 'store']);
    Route::patch('/{place}', [PlaceController::class, 'update']);
    Route::post('/{place}/default', [PlaceController::class, 'setDefault']);
    // Archiver, jamais supprimer : les interventions passées portent ce lieu.
    Route::delete('/{place}', [PlaceController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| API — Budget (E4), protection (E6) et assistant de commande (E5)
|--------------------------------------------------------------------------
|
| Le budget se consulte quand la question se pose — souvent en recevant une facture, sur son
| téléphone. La protection se consulte au pire moment : quand quelque chose vient de se casser,
| et qu'on n'est pas devant un ordinateur. L'assistant est fait pour ceux qui ne veulent pas
| naviguer dans un catalogue à deux niveaux — la situation par excellence d'un petit écran.
*/
Route::middleware('auth:sanctum')->prefix('client')->group(function () {
    Route::get('/budget', [HomeInsightsController::class, 'budget']);
    Route::get('/protection', [HomeInsightsController::class, 'protection']);
    // Sous drapeau : coupé, ce point répond 404 plutôt qu'une interprétation vide que
    // l'application lirait comme « l'assistant n'a rien compris ».
    Route::post('/order-intent', [HomeInsightsController::class, 'interpret']);
});

// ─────────────────────────────────────────────
// Parity map — shared across all authenticated roles
// GET /api/parity-map  (no /client prefix — intentional)
// ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->get('/parity-map', ParityMapController::class)->name('api.parity-map');
