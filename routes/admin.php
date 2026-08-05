<?php

use App\Http\Controllers\Admin\MissionAdminController;
use App\Http\Controllers\Admin\OnboardingDocumentController;
use App\Livewire\Admin\AccountingV2\AccountingCenter;
use App\Livewire\Admin\AdminAlertsCenter;
use App\Livewire\Admin\AdminAnalyticsDashboard;
use App\Livewire\Admin\AdminHomeDashboard;
use App\Livewire\Admin\AiDispatchCenter;
use App\Livewire\Admin\Analytics\AnalyticsCenter;
use App\Livewire\Admin\Analytics\CancellationReasonsCenter;
use App\Livewire\Admin\ApiTokensV2\ApiTokensCenter;
use App\Livewire\Admin\Audit\AuditCenter;
use App\Livewire\Admin\Availability\AvailabilityCenter;
use App\Livewire\Admin\B2BMonthlyInvoicesCenter;
use App\Livewire\Admin\Badges\BadgesCenter;
use App\Livewire\Admin\Bundles\BundlesCenter;
use App\Livewire\Admin\BusinessDashboard;
use App\Livewire\Admin\CancellationV2\CancellationsCenter;
use App\Livewire\Admin\ChatV2\ChatCenter;
use App\Livewire\Admin\ContractsV2\ContractsCenter;
use App\Livewire\Admin\CustomerCreditsManager;
use App\Livewire\Admin\Disputes\DisputesCenter;
use App\Livewire\Admin\EditRecurringBooking;
use App\Livewire\Admin\EnterpriseApprovalsCenter;
use App\Livewire\Admin\FeatureFlagsManager;
use App\Livewire\Admin\FleetV2\FleetCenter;
use App\Livewire\Admin\Fx\FxCenter;
use App\Livewire\Admin\Gdpr\GdprCenter;
use App\Livewire\Admin\GeolocationV2\GeolocationCenter;
use App\Livewire\Admin\GestionEntreprises;
use App\Livewire\Admin\GestionZones;
use App\Livewire\Admin\I18n\TranslationsCenter;
use App\Livewire\Admin\Insurance\InsuranceCenter;
use App\Livewire\Admin\KybV2\KybCenter;
use App\Livewire\Admin\Kyc\KycVerificationsCenter;
use App\Livewire\Admin\Loyalty\LoyaltyCenter;
use App\Livewire\Admin\Loyalty\LoyaltyRewardsCenter;
use App\Livewire\Admin\Marketing\MarketingCenter;
use App\Livewire\Admin\Matching\MatchingInsightsCenter;
use App\Livewire\Admin\MissionsAdmin;
use App\Livewire\Admin\NotificationPreferences\NotificationPreferencesCenter;
use App\Livewire\Admin\Nps\NpsCenter;
use App\Livewire\Admin\Onboarding\AdminOnboardingDocumentsCenter;
use App\Livewire\Admin\Onboarding\AdminOnboardingProvidersList;
use App\Livewire\Admin\OnboardingV2\OnboardingV2Center;
use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Livewire\Admin\OrderEngine\CountryCenter;
use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Livewire\Admin\OrderEngine\ZoneCenter;
use App\Livewire\Admin\OrganizationSitesManager;
use App\Livewire\Admin\Payments\StripeHardeningCenter;
use App\Livewire\Admin\PlatformReadiness;
use App\Livewire\Admin\Presence\PresenceCenter;
use App\Livewire\Admin\PricingV2\PricingCenter;
use App\Livewire\Admin\Promotions\PromoCampaignsCenter;
use App\Livewire\Admin\Promotions\PromoCodesCenter;
use App\Livewire\Admin\Promotions\ReferralsCenter;
use App\Livewire\Admin\Providers\ProviderRegistrationsCenter;
use App\Livewire\Admin\Push\PushCenter;
use App\Livewire\Admin\Quality\QualityCenter;
use App\Livewire\Admin\Ratings\RatingModerationCenter;
use App\Livewire\Admin\Realtime\RealtimeCenter;
use App\Livewire\Admin\Risk\RiskCenter;
use App\Livewire\Admin\Safety\SafetyCenter;
use App\Livewire\Admin\Sms\SmsCenter;
use App\Livewire\Admin\StripeConnectProviders;
use App\Livewire\Admin\SubscriptionsV2\SubscriptionsCenter;
use App\Livewire\Admin\Tips\TipsCenter;
use App\Livewire\Admin\Trades;
use App\Livewire\Admin\TradeZonePricingManager;
use App\Livewire\Admin\TripTracking\TripTrackingCenter;
use App\Livewire\Admin\UtilisateursAdmin;
use App\Livewire\Admin\WebhooksV2\WebhooksCenter;
use App\Livewire\AdminDashboard;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Request;

Route::middleware(['role:admin', 'enforce_2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
        Route::get('/home', AdminHomeDashboard::class)->name('home');

        // Récupération d'orphelins — pages admin auparavant non routées.
        Route::get('/zones', GestionZones::class)->name('zones');
        Route::get('/entreprises', GestionEntreprises::class)->name('entreprises');
        Route::get('/recurrence/{rendezVous}/serie', EditRecurringBooking::class)->name('recurrence.edit');

        if (class_exists(MissionsAdmin::class)) {
            Route::get('/missions', MissionsAdmin::class)->name('missions');
        } else {
            Route::get('/missions', function () {
                abort(501, 'La page missions admin n’est pas encore disponible.');
            })->name('missions');
        }

        if (class_exists(MissionAdminController::class)) {
            Route::get('/missions/{mission}', [MissionAdminController::class, 'show'])
                ->middleware('can:view,mission')
                ->name('missions.show');
        }

        Route::get('/missions/export/pdf', function () {
            if (class_exists(Pdf::class)) {
                $html = '
                    <h1>Export missions</h1>
                    <p>Export PDF temporaire. À remplacer par un vrai export filtré.</p>
                ';

                return Pdf::loadHTML($html)
                    ->download('missions-export.pdf');
            }

            abort(501, 'Export PDF missions pas encore implémenté.');
        })->name('missions.export.pdf');

        Route::get('/quality/export/incidents.csv', function () {
            return response()->streamDownload(function () {
                echo "id,mission_id,type,status,created_at\n";
            }, 'incidents.csv', [
                'Content-Type' => 'text/csv',
            ]);
        })->name('quality.export.incidents.csv');

        Route::get('/quality/export/missions.csv', function () {
            return response()->streamDownload(function () {
                echo "id,reference,status,quality_score,created_at\n";
            }, 'missions-quality.csv', [
                'Content-Type' => 'text/csv',
            ]);
        })->name('quality.export.missions.csv');

        Route::get('/rendez-vous/{rendezVous}', function (Booking $rendezVous) {
            if (Route::has('admin.missions')) {
                return redirect()->route('admin.missions');
            }

            return redirect()->route('admin.dashboard');
        })->name('rendezvous.show');

        $utilisateursAdmin = class_exists(UtilisateursAdmin::class)
            ? UtilisateursAdmin::class
            : function () {
                abort(501, 'La page gestion utilisateurs n’est pas encore disponible.');
            };

        Route::get('/utilisateurs', $utilisateursAdmin)
            ->name('utilisateurs.manage');

        /*
         * `/admin/users` (nom `admin.utilisateurs`) a été retiré le 2026-08-05 : c'était une
         * simple redirection vers `admin.utilisateurs.manage`, doublon d'un chemin qui n'a
         * qu'une raison d'être. Les anciens liens vers `/admin/users` répondent donc 404 ;
         * l'unique page reste `/admin/utilisateurs`.
         */

        if (class_exists(AdminAlertsCenter::class)) {
            Route::get('/alerts', AdminAlertsCenter::class)->name('alerts');
        }

        if (class_exists(AdminAnalyticsDashboard::class)) {
            Route::get('/analytics', AdminAnalyticsDashboard::class)->name('analytics');
        }

        if (class_exists(CustomerCreditsManager::class)) {
            Route::get('/credits-clients', CustomerCreditsManager::class)->name('customer.credits');
        }

        // Ratings — Modération des avis publics
        if (class_exists(RatingModerationCenter::class)) {
            Route::get('/avis', RatingModerationCenter::class)
                ->name('ratings.moderation');
        }

        // Matching v2 — Insights & simulator
        if (class_exists(MatchingInsightsCenter::class)) {
            Route::get('/matching', MatchingInsightsCenter::class)
                ->name('matching.insights');
        }

        // Stripe v2 — Hardening center (webhooks idempotents, reconciliation, failures)
        if (class_exists(StripeHardeningCenter::class)) {
            Route::get('/stripe', StripeHardeningCenter::class)
                ->name('stripe.hardening');
        }

        // i18n v2 — Centre de traductions (DB overrides + scan)
        if (class_exists(TranslationsCenter::class)) {
            Route::get('/translations', TranslationsCenter::class)
                ->name('translations.center');
        }

        // Disputes v2 — Centre de gestion des litiges (SLA, escalades, résolutions)
        if (class_exists(DisputesCenter::class)) {
            Route::get('/disputes', DisputesCenter::class)
                ->name('disputes.center');
        }

        // KYC v2 — Vérifications d'identité (Onfido / Veriff / Mock)
        if (class_exists(KycVerificationsCenter::class)) {
            Route::get('/kyc', KycVerificationsCenter::class)
                ->name('kyc.center');
        }

        // GDPR v2 — Compliance + audit log + retention
        if (class_exists(GdprCenter::class)) {
            Route::get('/gdpr', GdprCenter::class)
                ->name('gdpr.center');
        }

        // Loyalty v2 — Programme fidélité (tiers, members, adjustments)
        if (class_exists(LoyaltyCenter::class)) {
            Route::get('/loyalty', LoyaltyCenter::class)
                ->name('loyalty.center');
        }

        // Loyalty Rewards Marketplace — catalogue récompenses + rédemptions
        if (class_exists(LoyaltyRewardsCenter::class)) {
            Route::get('/loyalty/rewards', LoyaltyRewardsCenter::class)
                ->name('loyalty.rewards.center');
        }

        // Tips v2 — Centre des pourboires
        if (class_exists(TipsCenter::class)) {
            Route::get('/tips', TipsCenter::class)
                ->name('tips.center');
        }

        // Trip Tracking v2 — Sessions GPS missions (live + replay)
        if (class_exists(TripTrackingCenter::class)) {
            Route::get('/trip-tracking', TripTrackingCenter::class)
                ->name('trip-tracking.center');
        }

        // Presence v2 — Live online/offline status
        if (class_exists(PresenceCenter::class)) {
            Route::get('/presence', PresenceCenter::class)
                ->name('presence.center');
        }

        // Analytics — Raisons d'annulation
        if (class_exists(CancellationReasonsCenter::class)) {
            Route::get('/analytics/cancellations', CancellationReasonsCenter::class)
                ->name('analytics.cancellations');
        }

        // NPS Center
        if (class_exists(NpsCenter::class)) {
            Route::get('/nps', NpsCenter::class)->name('nps.center');
        }

        // UserSafety Admin (block/report moderation)
        if (class_exists(SafetyCenter::class)) {
            Route::get('/safety', SafetyCenter::class)->name('safety.center');
        }

        // Provider Badges Admin
        if (class_exists(BadgesCenter::class)) {
            Route::get('/badges', BadgesCenter::class)->name('badges.center');
        }

        // Multi-trade Bundles Center (orchestration chantiers groupés)
        if (class_exists(BundlesCenter::class)) {
            Route::get('/bundles', BundlesCenter::class)->name('bundles.center');
        }

        // SMS v2 — Centre SMS / WhatsApp (KPIs, recherche, retry manuel)
        if (class_exists(SmsCenter::class)) {
            Route::get('/sms', SmsCenter::class)
                ->name('sms.center');
        }

        // Push v2 — Centre Push notifications (FCM/APNs)
        if (class_exists(PushCenter::class)) {
            Route::get('/push', PushCenter::class)
                ->name('push.center');
        }

        // Realtime v2 — Centre Broadcast / Live (ledger + replay)
        if (class_exists(RealtimeCenter::class)) {
            Route::get('/realtime', RealtimeCenter::class)
                ->name('realtime.center');
        }

        // Analytics v2 — Centre Analytics produit (KPIs, funnel, top events)
        if (class_exists(AnalyticsCenter::class)) {
            Route::get('/analytics-v2', AnalyticsCenter::class)
                ->name('analytics.center');
        }

        // Availability v2 — Centre Calendrier providers
        if (class_exists(AvailabilityCenter::class)) {
            Route::get('/availability', AvailabilityCenter::class)
                ->name('availability.center');
        }

        // Risk v2 — Centre anti-fraude (évaluations + holds + review)
        if (class_exists(RiskCenter::class)) {
            Route::get('/risk', RiskCenter::class)
                ->name('risk.center');
        }

        // Marketing v2 — Segments + Campaigns + Recipients
        if (class_exists(MarketingCenter::class)) {
            Route::get('/marketing', MarketingCenter::class)
                ->name('marketing.center');
        }

        // Insurance v2 — Claims + Policies + Plans
        if (class_exists(InsuranceCenter::class)) {
            Route::get('/insurance', InsuranceCenter::class)
                ->name('insurance.center');
        }

        // FX v2 — Rates + Conversions + Currencies
        if (class_exists(FxCenter::class)) {
            Route::get('/fx', FxCenter::class)
                ->name('fx.center');
        }

        // Audit v2 — Events search + pin + export
        if (class_exists(AuditCenter::class)) {
            Route::get('/audit', AuditCenter::class)
                ->name('audit.center');
        }

        // Notifications Preferences v2 — Centre unifié channel × category
        if (class_exists(NotificationPreferencesCenter::class)) {
            Route::get('/notification-preferences', NotificationPreferencesCenter::class)
                ->name('notification-preferences.center');
        }

        // Quality v2 — Inspections terrain + validation admin
        if (class_exists(QualityCenter::class)) {
            Route::get('/quality', QualityCenter::class)
                ->name('quality.center');
        }

        // Cancellation v2 — Policies + cancellations + overrides
        if (class_exists(CancellationsCenter::class)) {
            Route::get('/cancellations-v2', CancellationsCenter::class)
                ->name('cancellations-v2.center');
        }

        // Onboarding v2 — Journeys + progress per user
        if (class_exists(OnboardingV2Center::class)) {
            Route::get('/onboarding-v2', OnboardingV2Center::class)
                ->name('onboarding-v2.center');
        }

        // Pricing v2 — Service catalog + rules + quotes + A/B experiments
        if (class_exists(PricingCenter::class)) {
            Route::get('/pricing-v2', PricingCenter::class)
                ->name('pricing-v2.center');
        }

        // Contracts v2 — Templates + documents + signatures
        if (class_exists(ContractsCenter::class)) {
            Route::get('/contracts-v2', ContractsCenter::class)
                ->name('contracts-v2.center');
        }

        // Webhooks v2 — Outbound B2B endpoints + events + deliveries
        if (class_exists(WebhooksCenter::class)) {
            Route::get('/webhooks-v2', WebhooksCenter::class)
                ->name('webhooks-v2.center');
        }

        // Geolocation v2 — Address autocomplete + geocoding + distance cache
        if (class_exists(GeolocationCenter::class)) {
            Route::get('/geolocation-v2', GeolocationCenter::class)
                ->name('geolocation-v2.center');
        }

        // API Tokens v2 — Personal access tokens + scopes + usage audit
        if (class_exists(ApiTokensCenter::class)) {
            Route::get('/api-tokens-v2', ApiTokensCenter::class)
                ->name('api-tokens-v2.center');
        }

        // Chat v2 — In-app messaging + moderation
        if (class_exists(ChatCenter::class)) {
            Route::get('/chat-v2', ChatCenter::class)
                ->name('chat-v2.center');
        }

        // Subscriptions v2 — Recurring billing
        if (class_exists(SubscriptionsCenter::class)) {
            Route::get('/subscriptions-v2', SubscriptionsCenter::class)
                ->name('subscriptions-v2.center');
        }

        // Accounting v2 — Ledger + periods + exports compta
        if (class_exists(AccountingCenter::class)) {
            Route::get('/accounting-v2', AccountingCenter::class)
                ->name('accounting-v2.center');
        }

        // KYB v2 — Compliance entreprises
        if (class_exists(KybCenter::class)) {
            Route::get('/kyb-v2', KybCenter::class)
                ->name('kyb-v2.center');
        }

        // Fleet v2 — Vehicles / Equipment / Assignments / Maintenance
        if (class_exists(FleetCenter::class)) {
            Route::get('/fleet-v2', FleetCenter::class)
                ->name('fleet-v2.center');
        }

        // Feature Flags — runtime toggle for config/features.php flags
        if (class_exists(FeatureFlagsManager::class)) {
            Route::get('/feature-flags', FeatureFlagsManager::class)
                ->name('feature-flags.manager');
        }

        // Promotions — Codes promo, campagnes, programme de parrainage
        Route::prefix('promotions')->name('promotions.')->group(function () {
            if (class_exists(PromoCodesCenter::class)) {
                Route::get('/codes', PromoCodesCenter::class)->name('codes');
            }
            if (class_exists(PromoCampaignsCenter::class)) {
                Route::get('/campagnes', PromoCampaignsCenter::class)->name('campaigns');
            }
            if (class_exists(ReferralsCenter::class)) {
                Route::get('/parrainages', ReferralsCenter::class)->name('referrals');
            }
        });

        if (class_exists(StripeConnectProviders::class)) {
            Route::get('/stripe-connect-providers', StripeConnectProviders::class)->name('stripe-connect.providers');
        }

        if (class_exists(AiDispatchCenter::class)) {
            Route::get('/ia-dispatch', AiDispatchCenter::class)->name('ai.dispatch');
        }

        if (class_exists(BusinessDashboard::class)) {
            Route::get('/business-dashboard', BusinessDashboard::class)->name('business.dashboard');
        }

        if (class_exists(PlatformReadiness::class)) {
            Route::get('/platform-readiness', PlatformReadiness::class)->name('platform.readiness');
        }

        if (class_exists(B2BMonthlyInvoicesCenter::class)) {
            Route::get('/b2b/facturation-mensuelle', B2BMonthlyInvoicesCenter::class)
                ->name('b2b.monthly-invoices');
        }

        if (class_exists(EnterpriseApprovalsCenter::class)) {
            Route::get('/approbations-entreprises', EnterpriseApprovalsCenter::class)
                ->name('enterprise.approvals');
        }

        if (class_exists(OrganizationSitesManager::class)) {
            Route::get('/sites', OrganizationSitesManager::class)->name('sites');
        }

        Route::get('/feedbacks/export', function () {
            $user = auth()->user();

            abort_unless($user && $user->isAdmin(), 403);

            if (class_exists(Pdf::class)) {
                return Pdf::loadHTML('<h1>Export feedbacks</h1>')
                    ->download('feedbacks.pdf');
            }

            return response('<h1>Export feedbacks</h1>', 200);
        })->name('feedbacks.export');

        Route::get('/feedbacks/export-csv', function () {
            $user = auth()->user();

            abort_unless($user && $user->isAdmin(), 403);

            $query = Feedback::query()
                ->with('rendezVous.serviceZone');

            if ($user->isZoneScopedAdmin()) {
                $query->whereHas('rendezVous', function ($q) use ($user) {
                    $q->where('service_zone_id', $user->managed_service_zone_id);
                });
            }

            $rows = $query->get();

            $csv = "id,rendez_vous_id,commentaire\n";

            foreach ($rows as $feedback) {
                $csv .= implode(',', [
                    $feedback->id,
                    $feedback->rendez_vous_id,
                    '"'.str_replace('"', '""', (string) ($feedback->commentaire ?? $feedback->comment ?? '')).'"',
                ])."\n";
            }

            return new class($csv, 200, ['Content-Type' => 'text/csv']) extends Response
            {
                public function prepare(Request $request): static
                {
                    parent::prepare($request);

                    $this->headers->set('Content-Type', 'text/csv', true);

                    return $this;
                }
            };
        })->name('feedbacks.export.csv');

        Route::get('/trades', Trades::class)->name('trades');

        Route::get('/trades/{trade}/pricing', TradeZonePricingManager::class)->name('trades.pricing');

        // Approbation des inscriptions prestataires en libre-service (app mobile).
        // Distinct de l'onboarding ci-dessous, qui valide le DOSSIER sur pièces : ici on ouvre
        // l'accès à un compte tout juste créé, que le middleware provider.approved bloque encore.
        Route::get('/inscriptions-prestataires', ProviderRegistrationsCenter::class)
            ->name('providers.registrations');

        // Phase 14.1 — Onboarding admin
        Route::get('/onboarding-providers', AdminOnboardingProvidersList::class)
            ->name('onboarding.providers');

        Route::get('/onboarding-documents', AdminOnboardingDocumentsCenter::class)
            ->name('onboarding.documents');

        // Téléchargement de fichier privé via URL signée temporaire
        Route::get('/onboarding-documents/{document}/file', [OnboardingDocumentController::class, 'show'])
            ->middleware('signed')
            ->name('onboarding.document.file');

        /*
         * Constructeur de parcours de commande.
         *
         * L'écran assemble le rendu client réel, le validateur et le moteur tarifaire : c'est là
         * qu'un responsable non technique écrit un questionnaire complet et voit, à droite, ce que
         * le client verra et le prix que ses réponses construisent.
         */
        /*
         * La descente : Pays → Zones → Secteurs & métiers.
         *
         * L'ancien écran n'est pas remplacé, il DESCEND d'un cran. Les liens existants vers
         * `/admin/catalogue` arrivent désormais sur la liste des pays — c'est voulu : un métier
         * s'active par zone, il n'y a donc plus de catalogue « en général » à afficher.
         */
        Route::get('/catalogue', CountryCenter::class)
            ->name('order-engine.catalog');

        Route::get('/catalogue/{country}', ZoneCenter::class)
            ->name('order-engine.zones');

        Route::get('/catalogue/{country}/{zone}', CatalogCenter::class)
            ->name('order-engine.catalog.zone');

        Route::get('/parcours/{trade}', QuestionnaireBuilder::class)
            ->name('order-engine.builder');
    });
