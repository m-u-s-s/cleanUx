<?php



use App\Models\User;
use App\Http\Controllers\Admin\MissionAdminController;
use App\Http\Controllers\Admin\OnboardingDocumentController;
use App\Livewire\Admin\Onboarding\AdminOnboardingDocumentsCenter;
use App\Livewire\Admin\Onboarding\AdminOnboardingProvidersList;
use App\Models\Feedback;
use App\Models\Booking;
use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;

Route::middleware(['role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', \App\Livewire\AdminDashboard::class)->name('dashboard');

        if (class_exists(\App\Livewire\Admin\MissionsAdmin::class)) {
            Route::get('/missions', \App\Livewire\Admin\MissionsAdmin::class)->name('missions');
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
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $html = '
                    <h1>Export missions</h1>
                    <p>Export PDF temporaire. À remplacer par un vrai export filtré.</p>
                ';

                return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
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

        $utilisateursAdmin = class_exists(\App\Livewire\Admin\UtilisateursAdmin::class)
            ? \App\Livewire\Admin\UtilisateursAdmin::class
            : function () {
                abort(501, 'La page gestion utilisateurs n’est pas encore disponible.');
            };

        Route::get('/utilisateurs', $utilisateursAdmin)
            ->name('utilisateurs.manage');

        Route::get('/users', function () {
            return redirect()->route('admin.utilisateurs.manage');
        })->name('utilisateurs');

        if (class_exists(\App\Livewire\Admin\AdminAlertsCenter::class)) {
            Route::get('/alerts', \App\Livewire\Admin\AdminAlertsCenter::class)->name('alerts');
        }

        if (class_exists(\App\Livewire\Admin\AdminAnalyticsDashboard::class)) {
            Route::get('/analytics', \App\Livewire\Admin\AdminAnalyticsDashboard::class)->name('analytics');
        }

        if (class_exists(\App\Livewire\Admin\CustomerCreditsManager::class)) {
            Route::get('/credits-clients', \App\Livewire\Admin\CustomerCreditsManager::class)->name('customer.credits');
        }

        // Ratings — Modération des avis publics
        if (class_exists(\App\Livewire\Admin\Ratings\RatingModerationCenter::class)) {
            Route::get('/avis', \App\Livewire\Admin\Ratings\RatingModerationCenter::class)
                ->name('ratings.moderation');
        }

        // Matching v2 — Insights & simulator
        if (class_exists(\App\Livewire\Admin\Matching\MatchingInsightsCenter::class)) {
            Route::get('/matching', \App\Livewire\Admin\Matching\MatchingInsightsCenter::class)
                ->name('matching.insights');
        }

        // Stripe v2 — Hardening center (webhooks idempotents, reconciliation, failures)
        if (class_exists(\App\Livewire\Admin\Payments\StripeHardeningCenter::class)) {
            Route::get('/stripe', \App\Livewire\Admin\Payments\StripeHardeningCenter::class)
                ->name('stripe.hardening');
        }

        // i18n v2 — Centre de traductions (DB overrides + scan)
        if (class_exists(\App\Livewire\Admin\I18n\TranslationsCenter::class)) {
            Route::get('/translations', \App\Livewire\Admin\I18n\TranslationsCenter::class)
                ->name('translations.center');
        }

        // Disputes v2 — Centre de gestion des litiges (SLA, escalades, résolutions)
        if (class_exists(\App\Livewire\Admin\Disputes\DisputesCenter::class)) {
            Route::get('/disputes', \App\Livewire\Admin\Disputes\DisputesCenter::class)
                ->name('disputes.center');
        }

        // KYC v2 — Vérifications d'identité (Onfido / Veriff / Mock)
        if (class_exists(\App\Livewire\Admin\Kyc\KycVerificationsCenter::class)) {
            Route::get('/kyc', \App\Livewire\Admin\Kyc\KycVerificationsCenter::class)
                ->name('kyc.center');
        }

        // GDPR v2 — Compliance + audit log + retention
        if (class_exists(\App\Livewire\Admin\Gdpr\GdprCenter::class)) {
            Route::get('/gdpr', \App\Livewire\Admin\Gdpr\GdprCenter::class)
                ->name('gdpr.center');
        }

        // Loyalty v2 — Programme fidélité (tiers, members, adjustments)
        if (class_exists(\App\Livewire\Admin\Loyalty\LoyaltyCenter::class)) {
            Route::get('/loyalty', \App\Livewire\Admin\Loyalty\LoyaltyCenter::class)
                ->name('loyalty.center');
        }

        // Loyalty Rewards Marketplace — catalogue récompenses + rédemptions
        if (class_exists(\App\Livewire\Admin\Loyalty\LoyaltyRewardsCenter::class)) {
            Route::get('/loyalty/rewards', \App\Livewire\Admin\Loyalty\LoyaltyRewardsCenter::class)
                ->name('loyalty.rewards.center');
        }

        // Tips v2 — Centre des pourboires
        if (class_exists(\App\Livewire\Admin\Tips\TipsCenter::class)) {
            Route::get('/tips', \App\Livewire\Admin\Tips\TipsCenter::class)
                ->name('tips.center');
        }

        // Trip Tracking v2 — Sessions GPS missions (live + replay)
        if (class_exists(\App\Livewire\Admin\TripTracking\TripTrackingCenter::class)) {
            Route::get('/trip-tracking', \App\Livewire\Admin\TripTracking\TripTrackingCenter::class)
                ->name('trip-tracking.center');
        }

        // Presence v2 — Live online/offline status
        if (class_exists(\App\Livewire\Admin\Presence\PresenceCenter::class)) {
            Route::get('/presence', \App\Livewire\Admin\Presence\PresenceCenter::class)
                ->name('presence.center');
        }

        // Analytics — Raisons d'annulation
        if (class_exists(\App\Livewire\Admin\Analytics\CancellationReasonsCenter::class)) {
            Route::get('/analytics/cancellations', \App\Livewire\Admin\Analytics\CancellationReasonsCenter::class)
                ->name('analytics.cancellations');
        }

        // SMS v2 — Centre SMS / WhatsApp (KPIs, recherche, retry manuel)
        if (class_exists(\App\Livewire\Admin\Sms\SmsCenter::class)) {
            Route::get('/sms', \App\Livewire\Admin\Sms\SmsCenter::class)
                ->name('sms.center');
        }

        // Push v2 — Centre Push notifications (FCM/APNs)
        if (class_exists(\App\Livewire\Admin\Push\PushCenter::class)) {
            Route::get('/push', \App\Livewire\Admin\Push\PushCenter::class)
                ->name('push.center');
        }

        // Realtime v2 — Centre Broadcast / Live (ledger + replay)
        if (class_exists(\App\Livewire\Admin\Realtime\RealtimeCenter::class)) {
            Route::get('/realtime', \App\Livewire\Admin\Realtime\RealtimeCenter::class)
                ->name('realtime.center');
        }

        // Analytics v2 — Centre Analytics produit (KPIs, funnel, top events)
        if (class_exists(\App\Livewire\Admin\Analytics\AnalyticsCenter::class)) {
            Route::get('/analytics-v2', \App\Livewire\Admin\Analytics\AnalyticsCenter::class)
                ->name('analytics.center');
        }

        // Availability v2 — Centre Calendrier providers
        if (class_exists(\App\Livewire\Admin\Availability\AvailabilityCenter::class)) {
            Route::get('/availability', \App\Livewire\Admin\Availability\AvailabilityCenter::class)
                ->name('availability.center');
        }

        // Risk v2 — Centre anti-fraude (évaluations + holds + review)
        if (class_exists(\App\Livewire\Admin\Risk\RiskCenter::class)) {
            Route::get('/risk', \App\Livewire\Admin\Risk\RiskCenter::class)
                ->name('risk.center');
        }

        // Marketing v2 — Segments + Campaigns + Recipients
        if (class_exists(\App\Livewire\Admin\Marketing\MarketingCenter::class)) {
            Route::get('/marketing', \App\Livewire\Admin\Marketing\MarketingCenter::class)
                ->name('marketing.center');
        }

        // Insurance v2 — Claims + Policies + Plans
        if (class_exists(\App\Livewire\Admin\Insurance\InsuranceCenter::class)) {
            Route::get('/insurance', \App\Livewire\Admin\Insurance\InsuranceCenter::class)
                ->name('insurance.center');
        }

        // FX v2 — Rates + Conversions + Currencies
        if (class_exists(\App\Livewire\Admin\Fx\FxCenter::class)) {
            Route::get('/fx', \App\Livewire\Admin\Fx\FxCenter::class)
                ->name('fx.center');
        }

        // Audit v2 — Events search + pin + export
        if (class_exists(\App\Livewire\Admin\Audit\AuditCenter::class)) {
            Route::get('/audit', \App\Livewire\Admin\Audit\AuditCenter::class)
                ->name('audit.center');
        }

        // Notifications Preferences v2 — Centre unifié channel × category
        if (class_exists(\App\Livewire\Admin\NotificationPreferences\NotificationPreferencesCenter::class)) {
            Route::get('/notification-preferences', \App\Livewire\Admin\NotificationPreferences\NotificationPreferencesCenter::class)
                ->name('notification-preferences.center');
        }

        // Quality v2 — Inspections terrain + validation admin
        if (class_exists(\App\Livewire\Admin\Quality\QualityCenter::class)) {
            Route::get('/quality', \App\Livewire\Admin\Quality\QualityCenter::class)
                ->name('quality.center');
        }

        // Cancellation v2 — Policies + cancellations + overrides
        if (class_exists(\App\Livewire\Admin\CancellationV2\CancellationsCenter::class)) {
            Route::get('/cancellations-v2', \App\Livewire\Admin\CancellationV2\CancellationsCenter::class)
                ->name('cancellations-v2.center');
        }

        // Onboarding v2 — Journeys + progress per user
        if (class_exists(\App\Livewire\Admin\OnboardingV2\OnboardingV2Center::class)) {
            Route::get('/onboarding-v2', \App\Livewire\Admin\OnboardingV2\OnboardingV2Center::class)
                ->name('onboarding-v2.center');
        }

        // Pricing v2 — Service catalog + rules + quotes + A/B experiments
        if (class_exists(\App\Livewire\Admin\PricingV2\PricingCenter::class)) {
            Route::get('/pricing-v2', \App\Livewire\Admin\PricingV2\PricingCenter::class)
                ->name('pricing-v2.center');
        }

        // Contracts v2 — Templates + documents + signatures
        if (class_exists(\App\Livewire\Admin\ContractsV2\ContractsCenter::class)) {
            Route::get('/contracts-v2', \App\Livewire\Admin\ContractsV2\ContractsCenter::class)
                ->name('contracts-v2.center');
        }

        // Webhooks v2 — Outbound B2B endpoints + events + deliveries
        if (class_exists(\App\Livewire\Admin\WebhooksV2\WebhooksCenter::class)) {
            Route::get('/webhooks-v2', \App\Livewire\Admin\WebhooksV2\WebhooksCenter::class)
                ->name('webhooks-v2.center');
        }

        // Geolocation v2 — Address autocomplete + geocoding + distance cache
        if (class_exists(\App\Livewire\Admin\GeolocationV2\GeolocationCenter::class)) {
            Route::get('/geolocation-v2', \App\Livewire\Admin\GeolocationV2\GeolocationCenter::class)
                ->name('geolocation-v2.center');
        }

        // API Tokens v2 — Personal access tokens + scopes + usage audit
        if (class_exists(\App\Livewire\Admin\ApiTokensV2\ApiTokensCenter::class)) {
            Route::get('/api-tokens-v2', \App\Livewire\Admin\ApiTokensV2\ApiTokensCenter::class)
                ->name('api-tokens-v2.center');
        }

        // Chat v2 — In-app messaging + moderation
        if (class_exists(\App\Livewire\Admin\ChatV2\ChatCenter::class)) {
            Route::get('/chat-v2', \App\Livewire\Admin\ChatV2\ChatCenter::class)
                ->name('chat-v2.center');
        }

        // Subscriptions v2 — Recurring billing
        if (class_exists(\App\Livewire\Admin\SubscriptionsV2\SubscriptionsCenter::class)) {
            Route::get('/subscriptions-v2', \App\Livewire\Admin\SubscriptionsV2\SubscriptionsCenter::class)
                ->name('subscriptions-v2.center');
        }

        // Accounting v2 — Ledger + periods + exports compta
        if (class_exists(\App\Livewire\Admin\AccountingV2\AccountingCenter::class)) {
            Route::get('/accounting-v2', \App\Livewire\Admin\AccountingV2\AccountingCenter::class)
                ->name('accounting-v2.center');
        }

        // Tenancy v2 — Multi-tenancy / White-label
        if (class_exists(\App\Livewire\Admin\TenancyV2\TenantsCenter::class)) {
            Route::get('/tenancy-v2', \App\Livewire\Admin\TenancyV2\TenantsCenter::class)
                ->name('tenancy-v2.center');
        }

        // KYB v2 — Compliance entreprises
        if (class_exists(\App\Livewire\Admin\KybV2\KybCenter::class)) {
            Route::get('/kyb-v2', \App\Livewire\Admin\KybV2\KybCenter::class)
                ->name('kyb-v2.center');
        }

        // Fleet v2 — Vehicles / Equipment / Assignments / Maintenance
        if (class_exists(\App\Livewire\Admin\FleetV2\FleetCenter::class)) {
            Route::get('/fleet-v2', \App\Livewire\Admin\FleetV2\FleetCenter::class)
                ->name('fleet-v2.center');
        }

        // Promotions — Codes promo, campagnes, programme de parrainage
        Route::prefix('promotions')->name('promotions.')->group(function () {
            if (class_exists(\App\Livewire\Admin\Promotions\PromoCodesCenter::class)) {
                Route::get('/codes', \App\Livewire\Admin\Promotions\PromoCodesCenter::class)->name('codes');
            }
            if (class_exists(\App\Livewire\Admin\Promotions\PromoCampaignsCenter::class)) {
                Route::get('/campagnes', \App\Livewire\Admin\Promotions\PromoCampaignsCenter::class)->name('campaigns');
            }
            if (class_exists(\App\Livewire\Admin\Promotions\ReferralsCenter::class)) {
                Route::get('/parrainages', \App\Livewire\Admin\Promotions\ReferralsCenter::class)->name('referrals');
            }
        });

        if (class_exists(\App\Livewire\Admin\StripeConnectProviders::class)) {
            Route::get('/stripe-connect-providers', \App\Livewire\Admin\StripeConnectProviders::class)->name('stripe-connect.providers');
        }

        if (class_exists(\App\Livewire\Admin\AiDispatchCenter::class)) {
            Route::get('/ia-dispatch', \App\Livewire\Admin\AiDispatchCenter::class)->name('ai.dispatch');
        }

        if (class_exists(\App\Livewire\Admin\BusinessDashboard::class)) {
            Route::get('/business-dashboard', \App\Livewire\Admin\BusinessDashboard::class)->name('business.dashboard');
        }

        if (class_exists(\App\Livewire\Admin\PlatformReadiness::class)) {
            Route::get('/platform-readiness', \App\Livewire\Admin\PlatformReadiness::class)->name('platform.readiness');
        }

        if (class_exists(\App\Livewire\Admin\B2BMonthlyInvoicesCenter::class)) {
            Route::get('/b2b/facturation-mensuelle', \App\Livewire\Admin\B2BMonthlyInvoicesCenter::class)
                ->name('b2b.monthly-invoices');
        }

        if (class_exists(\App\Livewire\Admin\EnterpriseApprovalsCenter::class)) {
            Route::get('/approbations-entreprises', \App\Livewire\Admin\EnterpriseApprovalsCenter::class)
                ->name('enterprise.approvals');
        }

        if (class_exists(\App\Livewire\Admin\OrganizationSitesManager::class)) {
            Route::get('/sites', \App\Livewire\Admin\OrganizationSitesManager::class)->name('sites');
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
                    '"' . str_replace('"', '""', (string) ($feedback->commentaire ?? $feedback->comment ?? '')) . '"',
                ]) . "\n";
            }

            return new class($csv, 200, ['Content-Type' => 'text/csv']) extends \Illuminate\Http\Response {
                public function prepare(\Symfony\Component\HttpFoundation\Request $request): static
                {
                    parent::prepare($request);

                    $this->headers->set('Content-Type', 'text/csv', true);

                    return $this;
                }
            };
        })->name('feedbacks.export.csv');

        Route::get('/trades', \App\Livewire\Admin\Trades::class)->name('trades');

        // Phase 14.1 — Onboarding admin
        Route::get('/onboarding-providers',  AdminOnboardingProvidersList::class)
            ->name('onboarding.providers');

        Route::get('/onboarding-documents',  AdminOnboardingDocumentsCenter::class)
            ->name('onboarding.documents');

        // Téléchargement de fichier privé via URL signée temporaire
        Route::get('/onboarding-documents/{document}/file', [OnboardingDocumentController::class, 'show'])
            ->middleware('signed')
            ->name('onboarding.document.file');
    });
