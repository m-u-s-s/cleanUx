<?php

use App\Http\Controllers\Api\EmployeeMissionTrackingController;
use App\Http\Controllers\Api\PhoneVerificationController;
use App\Http\Controllers\Api\Provider\AsapOfferController;
use App\Http\Controllers\Api\Provider\AvailabilityController;
use App\Http\Controllers\Api\Provider\BadgesController;
use App\Http\Controllers\Api\Provider\FleetProviderController;
use App\Http\Controllers\Api\Provider\KycController;
use App\Http\Controllers\Api\Provider\MissionLiveTrackingController;
use App\Http\Controllers\Api\Provider\PresenceController;
use App\Http\Controllers\Api\Provider\ProviderCancellationController;
use App\Http\Controllers\Api\Provider\ProviderDisputeController;
use App\Http\Controllers\Api\Provider\ProviderMissionLifecycleController;
use App\Http\Controllers\Api\Provider\ProviderOnboardingController;
use App\Http\Controllers\Api\Provider\ProviderPayoutsController;
use App\Http\Controllers\Api\Provider\ProviderPerformanceController;
use App\Http\Controllers\Api\Provider\ProviderProfileController;
use App\Http\Controllers\Api\Provider\ProviderRatingController;
use App\Http\Controllers\Api\Provider\ProviderWalletController;
use App\Http\Controllers\Api\Provider\QualityInspectionController;
use App\Http\Controllers\Api\Provider\StripeConnectController;
use App\Http\Controllers\Api\Provider\TripTrackingController;
use App\Http\Controllers\Api\ProviderMissionAssignmentController;
use App\Http\Controllers\Api\ProviderPresenceController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Authenticated — Provider endpoints
// ─────────────────────────────────────────────

// M1 — defense-in-depth: gate the whole provider surface by role in addition to the
// per-controller ownership checks, so a client account can never reach provider endpoints
// (which is what turned the Quality module's missing checks into an exploitable IDOR).
//
// `provider.approved` ajoute une seconde condition pour les comptes créés par l'inscription en
// libre-service de l'app prestataire : ils restent cantonnés à leur dossier tant qu'un humain
// ne les a pas approuvés. Les routes nécessaires à ce dossier s'en excluent explicitement
// (withoutMiddleware ci-dessous), sans quoi le compte serait enfermé hors de tout — c'est
// exactement le défaut que ce lot corrige.
Route::middleware(['auth:sanctum', 'role:employe', 'provider.approved'])->group(function () {

    // Phase 0 — Mission tracking (existant)
    Route::post('/missions/{mission}/tracking/start', [EmployeeMissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/push', [EmployeeMissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/stop', [EmployeeMissionTrackingController::class, 'stop']);

    // Phase 11 — Provider presence (binary on/off)
    Route::prefix('provider/presence')->group(function () {
        Route::post('/online', [ProviderPresenceController::class, 'online']);
        Route::post('/offline', [ProviderPresenceController::class, 'offline']);
        Route::post('/heartbeat', [ProviderPresenceController::class, 'heartbeat']);
        Route::get('/me', [ProviderPresenceController::class, 'me']);
    });

    // Phase 11 — Mission accept/decline
    Route::prefix('provider/assignments')->group(function () {
        Route::get('/inbox', [ProviderMissionAssignmentController::class, 'inbox']);
        Route::get('/{assignment}', [ProviderMissionAssignmentController::class, 'show']);
        Route::post('/{assignment}/accept', [ProviderMissionAssignmentController::class, 'accept']);
        Route::post('/{assignment}/decline', [ProviderMissionAssignmentController::class, 'decline']);
    });

    // Phase Ratings + Performance + Live Tracking + Trip Tracking + Badges + Presence v2 + Quality + Availability + Wallet + Disputes + KYC + Profile + Stripe Connect
    Route::prefix('provider')->group(function () {

        // Phase Ratings — Avis provider → client + réponse aux avis reçus
        Route::get('/ratings/me', [ProviderRatingController::class, 'mine']);
        Route::post('/bookings/{booking}/rating', [ProviderRatingController::class, 'submit']);
        Route::post('/ratings/{feedback}/respond', [ProviderRatingController::class, 'respond']);

        // Phase Matching v2 — Performance metrics du provider
        Route::get('/performance/me', [ProviderPerformanceController::class, 'me']);

        // Phase Realtime v2 — Live position / ETA broadcasts pendant une mission
        Route::post('/missions/{mission}/live/position', [MissionLiveTrackingController::class, 'pushPosition']);
        Route::post('/missions/{mission}/live/eta', [MissionLiveTrackingController::class, 'pushEta']);

        // Trip Tracking v2 — sessions GPS persistées + auto-ETA + geofence
        Route::post('/bookings/{booking}/tracking/start', [TripTrackingController::class, 'start']);
        Route::post('/tracking/{session}/ping', [TripTrackingController::class, 'ping']);
        Route::post('/tracking/{session}/in-mission', [TripTrackingController::class, 'markInMission']);
        Route::post('/tracking/{session}/confirm-presence', [TripTrackingController::class, 'confirmPresence']);
        Route::post('/tracking/{session}/pause', [TripTrackingController::class, 'pause']);
        Route::post('/tracking/{session}/resume', [TripTrackingController::class, 'resume']);
        Route::post('/tracking/{session}/end', [TripTrackingController::class, 'end']);

        // Provider Badges (read + manual re-evaluate)
        Route::get('/badges', [BadgesController::class, 'mine']);
        Route::post('/badges/evaluate', [BadgesController::class, 'evaluate']);

        // Presence v2 — Online/Busy/Break/Offline (4 états, coexiste avec Phase 11 binary on/off)
        Route::get('/presence-v2', [PresenceController::class, 'status']);
        Route::post('/presence-v2/online', [PresenceController::class, 'goOnline']);
        Route::post('/presence-v2/heartbeat', [PresenceController::class, 'heartbeat']);
        Route::post('/presence-v2/busy', [PresenceController::class, 'goBusy']);
        Route::post('/presence-v2/break', [PresenceController::class, 'goBreak']);
        Route::post('/presence-v2/offline', [PresenceController::class, 'goOffline']);

        // Courses immédiates proposées au prestataire — premier arrivé, premier servi.
        Route::get('/asap-offers', [AsapOfferController::class, 'index']);
        Route::post('/asap-offers/{asapRequest}/accept', [AsapOfferController::class, 'accept']);
        Route::post('/asap-offers/{asapRequest}/decline', [AsapOfferController::class, 'decline']);

        // Phase Quality v2 — Inspections (provider terrain)
        Route::get('/missions/{mission}/inspections', [QualityInspectionController::class, 'index']);
        Route::post('/missions/{mission}/inspections', [QualityInspectionController::class, 'start']);
        Route::get('/inspections/{inspection}', [QualityInspectionController::class, 'show']);
        Route::put('/inspections/{inspection}/items/{checklistItem}', [QualityInspectionController::class, 'submitItem']);
        Route::post('/inspections/{inspection}/photos', [QualityInspectionController::class, 'uploadPhoto']);
        Route::post('/inspections/{inspection}/submit', [QualityInspectionController::class, 'submit']);

        // Phase Availability v2 — Calendrier provider (slots récurrents + exceptions + iCal)
        Route::get('/availability', [AvailabilityController::class, 'index']);
        Route::get('/availability/windows', [AvailabilityController::class, 'windows']);
        Route::get('/availability/ical', [AvailabilityController::class, 'ical']);
        Route::post('/availability/slots', [AvailabilityController::class, 'storeSlot']);
        Route::put('/availability/slots/{slot}', [AvailabilityController::class, 'updateSlot']);
        Route::delete('/availability/slots/{slot}', [AvailabilityController::class, 'destroySlot']);
        Route::post('/availability/exceptions', [AvailabilityController::class, 'storeException']);
        Route::delete('/availability/exceptions/{exception}', [AvailabilityController::class, 'destroyException']);

        // Phase Stripe v2 — Wallet provider
        Route::get('/wallet/balance', [ProviderWalletController::class, 'balance']);
        Route::get('/wallet/transactions', [ProviderWalletController::class, 'transactions']);
        Route::post('/wallet/withdraw', [ProviderWalletController::class, 'withdraw']);

        // Phase Disputes v2 — Litiges provider
        Route::get('/disputes', [ProviderDisputeController::class, 'index']);
        Route::post('/disputes/{dispute}/respond', [ProviderDisputeController::class, 'respond']);

        // Constitution du dossier prestataire : accessible à un compte auto-inscrit encore en
        // attente d'approbation. Vérifier son identité, renseigner son profil et brancher ses
        // paiements sont précisément les étapes qui mènent à l'approbation — les fermer
        // rendrait le compte impossible à faire avancer.
        Route::withoutMiddleware('provider.approved')->group(function () {

            // Phase KYC v2 — Vérification d'identité
            Route::post('/kyc/start', [KycController::class, 'start']);
            Route::get('/kyc/status', [KycController::class, 'status']);
            Route::post('/kyc/verifications/{verification}/sync', [KycController::class, 'sync']);

            // Mobile app — provider profile self-update (name, phone, locale)
            Route::put('/profile', [ProviderProfileController::class, 'update']);

            // Miroir des routes OTP client. Le téléphone est vérifié dès l'inscription, mais un
            // prestataire qui change de numéro en cours de dossier doit pouvoir le re-vérifier
            // sans attendre son approbation — le numéro est ce par quoi le client le joint.
            Route::post('/phone/verify-request', [PhoneVerificationController::class, 'requestCode'])
                ->middleware('throttle:otp');
            Route::post('/phone/verify-confirm', [PhoneVerificationController::class, 'confirm'])
                ->middleware('throttle:otp');

            // Sprint 0 — Task 3: Stripe Connect provider endpoints (RN Phase 2)
            Route::prefix('stripe-connect')->middleware('token.grace')->group(function () {
                Route::get('/status', [StripeConnectController::class, 'status']);
                Route::post('/onboard', [StripeConnectController::class, 'onboard']);
                Route::get('/payouts', [StripeConnectController::class, 'payouts']);
                Route::get('/dashboard-link', [StripeConnectController::class, 'dashboardLink']);
            });
        });

        // Fleet v2 — Provider my-vehicles
        Route::get('/fleet/my-vehicles', [FleetProviderController::class, 'myVehicles']);

        // Fleet — Provider return assignment
        Route::post('/fleet/assignments/{assignment}/return', [FleetProviderController::class, 'returnAssignment']);
    });

    // Phase 12 — Mission lifecycle (start/arrive/complete)
    Route::prefix('provider/missions')->group(function () {
        Route::get('/active', [ProviderMissionLifecycleController::class, 'active']);
        Route::get('/{mission}', [ProviderMissionLifecycleController::class, 'show']);
        Route::post('/{mission}/start', [ProviderMissionLifecycleController::class, 'start']);
        Route::post('/{mission}/arrive', [ProviderMissionLifecycleController::class, 'arrive']);
        // arrived -> started. N'existait que sur les routes web a session, donc hors de portee de
        // l'app mobile : un prestataire arrive sur place ne pouvait pas demarrer sa mission.
        Route::post('/{mission}/begin', [ProviderMissionLifecycleController::class, 'begin']);
        Route::post('/{mission}/complete', [ProviderMissionLifecycleController::class, 'complete']);
        // Clôture par le code que le client affiche : un SMS voyage, un écran non.
        Route::post('/{mission}/complete-by-qr', [ProviderMissionLifecycleController::class, 'completeByQr']);
    });

    // Phase 14 — Cancellation provider
    Route::prefix('provider/missions')->group(function () {
        Route::post('/{mission}/cancel', [ProviderCancellationController::class, 'cancel']);
        Route::post('/{mission}/no-show', [ProviderCancellationController::class, 'noShow']);
    });

    // Phase 14 — Onboarding provider. Ouvert aux comptes en attente d'approbation : c'est la
    // raison d'être de ce lot, un compte auto-inscrit doit pouvoir constituer son dossier.
    Route::prefix('provider/onboarding')->withoutMiddleware('provider.approved')->group(function () {
        Route::post('/start', [ProviderOnboardingController::class, 'start']);
        Route::get('/progress', [ProviderOnboardingController::class, 'progress']);
        Route::post('/profile', [ProviderOnboardingController::class, 'setProfile']);
        // Justificatifs exigés de CE prestataire, chacun avec son état. Rien ne permettait de
        // relire ses propres documents : ni « en cours de vérification » ni « refusé + motif »
        // n'étaient affichables, alors que c'est ce qui permet de corriger un dossier.
        Route::get('/documents', [ProviderOnboardingController::class, 'documents']);
        Route::post('/documents', [ProviderOnboardingController::class, 'uploadDocument']);
        Route::post('/tax', [ProviderOnboardingController::class, 'setTax']);
        Route::post('/skills', [ProviderOnboardingController::class, 'setSkills']);
        // Référentiel des zones d'intervention. `setSkills` acceptait `service_zone_ids` depuis
        // toujours, mais rien ne permettait de savoir quelles zones existent : aucun écran ne
        // pouvait donc en proposer, et le matching géographique restait sans données.
        Route::get('/service-zones', [ProviderOnboardingController::class, 'serviceZones']);
    });
});

// Payouts — token.grace middleware
Route::middleware(['auth:sanctum', 'token.grace'])->prefix('provider')->group(function () {
    Route::get('/payouts', [ProviderPayoutsController::class, 'index']);
    Route::get('/payouts/summary', [ProviderPayoutsController::class, 'summary']);
});

// Admin — Onboarding document file download (web session auth + role:admin)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/onboarding-documents/{document}/file', [ProviderOnboardingController::class, 'downloadDocument'])
        ->name('admin.onboarding.document.file');
});
