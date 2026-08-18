<?php

namespace App\Providers;

use App\Admin\Console\ReportRegistry;
use App\Admin\Console\ResourceRegistry;
use App\Admin\Reports\AlertsReport;
use App\Admin\Reports\AnalyticsReport;
use App\Admin\Reports\AutomationReport;
use App\Admin\Reports\BusinessReport;
use App\Admin\Reports\DashboardReport;
use App\Admin\Reports\DispatchCenterReport;
use App\Admin\Reports\FinanceReport;
use App\Admin\Reports\HomeReport;
use App\Admin\Reports\MarketplaceHealthReport;
use App\Admin\Reports\ReadinessReport;
use App\Admin\Reports\SubscriptionsReport;
use App\Admin\Reports\ToolsReport;
use App\Admin\Resources\AccountingEntryResource;
use App\Admin\Resources\AddressLookupResource;
use App\Admin\Resources\AnalyticsEventResource;
use App\Admin\Resources\ApiTokenUsageResource;
use App\Admin\Resources\ApiTokenV2Resource;
use App\Admin\Resources\AuditEventResource;
use App\Admin\Resources\AvailabilitySlotResource;
use App\Admin\Resources\BadgeResource;
use App\Admin\Resources\BookingResource;
use App\Admin\Resources\BroadcastEventResource;
use App\Admin\Resources\BundleResource;
use App\Admin\Resources\CalendarResource;
use App\Admin\Resources\CancellationPolicyResource;
use App\Admin\Resources\CancellationQuestionResource;
use App\Admin\Resources\CancellationReasonResource;
use App\Admin\Resources\ChatThreadResource;
use App\Admin\Resources\CompanyResource;
use App\Admin\Resources\ContractDocumentResource;
use App\Admin\Resources\ContractRateCardResource;
use App\Admin\Resources\ContractSignatureResource;
use App\Admin\Resources\CountryResource;
use App\Admin\Resources\CountrySettingResource;
use App\Admin\Resources\CustomerCreditResource;
use App\Admin\Resources\DispatchResource;
use App\Admin\Resources\DisputeResource;
use App\Admin\Resources\EmailLogResource;
use App\Admin\Resources\EnterpriseApprovalResource;
use App\Admin\Resources\EnterpriseWorkOrderResource;
use App\Admin\Resources\ExchangeRateResource;
use App\Admin\Resources\FeatureFlagResource;
use App\Admin\Resources\FeedbackResource;
use App\Admin\Resources\FieldTeamResource;
use App\Admin\Resources\FinanceInvoiceResource;
use App\Admin\Resources\FleetEquipmentResource;
use App\Admin\Resources\GdprRequestResource;
use App\Admin\Resources\InsuranceClaimResource;
use App\Admin\Resources\KybResource;
use App\Admin\Resources\KycResource;
use App\Admin\Resources\LoyaltyAccountResource;
use App\Admin\Resources\MarketingCampaignResource;
use App\Admin\Resources\MatchingDecisionResource;
use App\Admin\Resources\MissionBatchResource;
use App\Admin\Resources\MissionResource;
use App\Admin\Resources\NotificationPreferenceResource;
use App\Admin\Resources\NpsResource;
use App\Admin\Resources\OnboardingDocumentResource;
use App\Admin\Resources\OnboardingProgressResource;
use App\Admin\Resources\OrganizationContractResource;
use App\Admin\Resources\PlanningResource;
use App\Admin\Resources\PlatformModuleResource;
use App\Admin\Resources\PremiumClientResource;
use App\Admin\Resources\PresenceResource;
use App\Admin\Resources\PriceQuoteResource;
use App\Admin\Resources\PromoCampaignResource;
use App\Admin\Resources\PromoCodeResource;
use App\Admin\Resources\ProviderOnboardingResource;
use App\Admin\Resources\ProviderRegistrationResource;
use App\Admin\Resources\PushNotificationResource;
use App\Admin\Resources\QualityInspectionResource;
use App\Admin\Resources\RatingReportResource;
use App\Admin\Resources\ReferralResource;
use App\Admin\Resources\RentalVehicleResource;
use App\Admin\Resources\RiskEvaluationResource;
use App\Admin\Resources\RiskHoldResource;
use App\Admin\Resources\SectorResource;
use App\Admin\Resources\ServiceCatalogResource;
use App\Admin\Resources\SiteResource;
use App\Admin\Resources\SmsMessageResource;
use App\Admin\Resources\StripeConnectResource;
use App\Admin\Resources\StripeWebhookEventResource;
use App\Admin\Resources\TipResource;
use App\Admin\Resources\TradeResource;
use App\Admin\Resources\TranslationResource;
use App\Admin\Resources\TripTrackingResource;
use App\Admin\Resources\UserReportResource;
use App\Admin\Resources\UserResource;
use App\Admin\Resources\WebhookEndpointResource;
use App\Admin\Resources\ZoneResource;
use Illuminate\Support\ServiceProvider;

/**
 * Enregistre les descripteurs de la console d'administration.
 *
 * C'est le SEUL endroit où l'on déclare qu'un domaine est servi par le moteur. Ajouter un
 * descripteur ici sans basculer `coverage` sur `descriptor` dans `config/admin_console.php` — ou
 * l'inverse — fait échouer `ResourceRegistryTest`. Les deux gestes vont ensemble, délibérément :
 * c'est ce qui empêche l'annuaire d'annoncer un module que rien ne sait rendre.
 */
class AdminConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton : chaque instanciation reconstruirait tous les descripteurs, et une liste
        // d'annuaire en ferait autant de fois qu'elle a de lignes.
        $this->app->singleton(ResourceRegistry::class, function ($app) {
            $registry = new ResourceRegistry($app);

            // Un appel par domaine servi. La clé doit exister dans `config/admin_console.php` ET
            // y porter `coverage => 'descriptor'` : ResourceRegistryTest refuse les deux écarts.

            // Lot 1 — le CRUD complet : liste, formulaire, édition, suppression.
            /*
             * Les modèles servis par une page web MULTI-MODÈLES.
             *
             * « Opérations B2B » gère contrats, ordres de travail et grilles tarifaires ; le risque
             * montre des évaluations ET des blocages ; les contrats, des documents ET des
             * signatures. Le moteur sert un modèle par descripteur : sans ces clés, les gestes les
             * plus fréquents de ces pages n'avaient nulle part où vivre.
             */
            $registry->register('api-tokens-list', ApiTokenV2Resource::class);
            $registry->register('risk-holds', RiskHoldResource::class);
            $registry->register('contract-signatures', ContractSignatureResource::class);
            $registry->register('b2b-contracts', OrganizationContractResource::class);
            $registry->register('b2b-work-orders', EnterpriseWorkOrderResource::class);

            $registry->register('users', UserResource::class);
            $registry->register('companies', CompanyResource::class);
            $registry->register('sites', SiteResource::class);

            // Lot 2 — les files de DÉCISION : pas de formulaire, des actions déléguées aux
            // services qui portent la règle. Aucun refus ici : tous exigent un motif écrit, et le
            // moteur ne sait pas demander une valeur avant d'agir (sous-projet C).
            $registry->register('kyc', KycResource::class);
            $registry->register('kyb', KybResource::class);
            $registry->register('enterprise-approvals', EnterpriseApprovalResource::class);
            $registry->register('disputes', DisputeResource::class);

            // Lot 3 — création simple et bascules. Aucun de ces trois domaines ne SUPPRIME : on
            // suspend, on désactive. Les rachats, attributions et historiques déjà posés pointent
            // sur ces lignes, et les effacer laisserait des références sans explication.
            $registry->register('promo-codes', PromoCodeResource::class);
            $registry->register('badges', BadgeResource::class);
            $registry->register('feature-flags', FeatureFlagResource::class);

            /*
             * Lot 4 — les domaines adossés à un modèle unique, décrits par `EloquentResource`.
             *
             * Chacun déclare ses colonnes, sa recherche et ses filtres ; le squelette est
             * mutualisé. `EloquentResourceSchemaTest` confronte chaque colonne déclarée au schéma
             * réel : une colonne mal nommée afficherait « — » sur toute la colonne sans que rien
             * ne le signale.
             */
            $registry->register('nps', NpsResource::class);
            $registry->register('feedbacks', FeedbackResource::class);
            $registry->register('analytics-v2', AnalyticsEventResource::class);
            $registry->register('realtime', BroadcastEventResource::class);
            $registry->register('audit', AuditEventResource::class);
            $registry->register('translations', TranslationResource::class);
            $registry->register('emails', EmailLogResource::class);
            $registry->register('sms', SmsMessageResource::class);
            $registry->register('tips', TipResource::class);
            $registry->register('zones', ZoneResource::class);
            $registry->register('countries', CountryResource::class);
            $registry->register('trades', TradeResource::class);

            /*
             * Lot 5 — journaux, files et registres. Aucun n'expose d'action : chacun dit dans son
             * en-tête pourquoi la décision qui lui correspond vit ailleurs, dans le module qui en
             * porte les effets de bord.
             */
            $registry->register('risk', RiskEvaluationResource::class);
            $registry->register('contracts', ContractDocumentResource::class);
            $registry->register('marketing', MarketingCampaignResource::class);
            $registry->register('promo-campaigns', PromoCampaignResource::class);
            $registry->register('referrals', ReferralResource::class);
            $registry->register('loyalty', LoyaltyAccountResource::class);
            $registry->register('ratings', RatingReportResource::class);
            $registry->register('push', PushNotificationResource::class);
            $registry->register('notification-preferences', NotificationPreferenceResource::class);
            $registry->register('gdpr', GdprRequestResource::class);
            $registry->register('api-tokens', ApiTokenUsageResource::class);
            $registry->register('webhooks', WebhookEndpointResource::class);
            $registry->register('geolocation', AddressLookupResource::class);
            $registry->register('chat', ChatThreadResource::class);

            /*
             * Lot 6 — opérations, argent et conformité. Presque tous en lecture seule, et pour la
             * même raison : ces tables portent des PREUVES (exécution, explicabilité d'un prix,
             * équilibre comptable, verdict géographique). Les modifier depuis une liste
             * effacerait ce qu'elles servent à établir.
             */
            $registry->register('availability', AvailabilitySlotResource::class);
            $registry->register('presence', PresenceResource::class);
            $registry->register('trip-tracking', TripTrackingResource::class);
            $registry->register('matching', MatchingDecisionResource::class);
            $registry->register('quality', QualityInspectionResource::class);
            $registry->register('safety', UserReportResource::class);
            $registry->register('teams', FieldTeamResource::class);
            $registry->register('onboarding-documents', OnboardingDocumentResource::class);
            $registry->register('onboarding-v2', OnboardingProgressResource::class);
            $registry->register('services', ServiceCatalogResource::class);
            $registry->register('bundles', BundleResource::class);
            $registry->register('accounting', AccountingEntryResource::class);
            $registry->register('b2b-invoices', FinanceInvoiceResource::class);
            $registry->register('credits', CustomerCreditResource::class);
            $registry->register('fx', ExchangeRateResource::class);
            $registry->register('stripe', StripeWebhookEventResource::class);
            $registry->register('insurance', InsuranceClaimResource::class);
            $registry->register('cancellations', CancellationPolicyResource::class);
            $registry->register('fleet', FleetEquipmentResource::class);

            // NOS LOCATIONS -- le parc vendu aux clients, distinct de la flotte interne ci-dessus.
            $registry->register('rentals', RentalVehicleResource::class);
            $registry->register('platform-modules', PlatformModuleResource::class);
            $registry->register('international', CountrySettingResource::class);
            $registry->register('b2b-operations', ContractRateCardResource::class);
            $registry->register('missions', MissionResource::class);
            $registry->register('pricing', PriceQuoteResource::class);

            /*
             * Lot 7 — les lectures opérationnelles. Plusieurs partagent une table en répondant à
             * des questions différentes (les rendez-vous, le planning, le calendrier, le dispatch
             * lisent tous `bookings`) : ce sont des VUES distinctes, pas des doublons. Une seule
             * liste obligerait à re-filtrer à chaque fois pour retrouver la sienne.
             */
            $registry->register('bookings', BookingResource::class);
            $registry->register('planning', PlanningResource::class);
            $registry->register('calendar', CalendarResource::class);
            $registry->register('ia-dispatch', DispatchResource::class);
            $registry->register('cancellation-reasons', CancellationReasonResource::class);
            /*
             * LE QUESTIONNAIRE, distinct de la liste des motifs juste au-dessus : celle-ci REGARDE
             * ce qui s'est passé, celui-là DÉCIDE ce qu'on demandera demain. Ses écritures passent
             * par `CancellationQuestionnaireService`, jamais par la colonne.
             */
            $registry->register('cancellation-questions', CancellationQuestionResource::class);
            $registry->register('provider-registrations', ProviderRegistrationResource::class);
            $registry->register('onboarding-providers', ProviderOnboardingResource::class);
            $registry->register('stripe-connect', StripeConnectResource::class);
            $registry->register('premium', PremiumClientResource::class);
            $registry->register('orchestration', MissionBatchResource::class);
            $registry->register('catalog', SectorResource::class);

            return $registry;
        });

        /*
         * Les rapports — les modules qui ne sont pas des listes.
         *
         * Dix pages d'administration n'ont aucune table derrière elles : ce sont des synthèses.
         * Les forcer dans le moteur de liste aurait demandé d'inventer une entité inexistante,
         * et l'écran aurait montré une liste vide en prétendant couvrir le domaine.
         */
        $this->app->singleton(ReportRegistry::class, function ($app) {
            $reports = new ReportRegistry($app);

            $reports->register('dashboard', DashboardReport::class);
            /*
             * LA SANTÉ DU MARCHÉ (E29, E30, E28). `coverage => 'report'` dans le registre est une
             * AFFIRMATION : un module qui l'annonce sans rapport enregistré ouvrirait un écran vide
             * sur mobile. Le garde-fou l'a rattrapé aussitôt — et il avait raison.
             */
            $reports->register('marketplace-health', MarketplaceHealthReport::class);
            $reports->register('home', HomeReport::class);
            $reports->register('business', BusinessReport::class);
            $reports->register('alerts', AlertsReport::class);
            $reports->register('analytics', AnalyticsReport::class);
            $reports->register('readiness', ReadinessReport::class);
            $reports->register('tools', ToolsReport::class);
            $reports->register('automation', AutomationReport::class);
            $reports->register('finance', FinanceReport::class);
            $reports->register('subscriptions', SubscriptionsReport::class);
            // La répartition : ce n'est pas une liste de lignes d'une table. « Combien de clients
            // ont attendu pour rien » se lit en croisant les recherches et les offres.
            $reports->register('dispatch-center', DispatchCenterReport::class);

            return $reports;
        });
    }
}
