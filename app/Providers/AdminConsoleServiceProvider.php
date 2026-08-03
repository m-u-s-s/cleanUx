<?php

namespace App\Providers;

use App\Admin\Console\ResourceRegistry;
use App\Admin\Resources\AnalyticsEventResource;
use App\Admin\Resources\AuditEventResource;
use App\Admin\Resources\BadgeResource;
use App\Admin\Resources\BroadcastEventResource;
use App\Admin\Resources\CompanyResource;
use App\Admin\Resources\CountryResource;
use App\Admin\Resources\DisputeResource;
use App\Admin\Resources\EmailLogResource;
use App\Admin\Resources\EnterpriseApprovalResource;
use App\Admin\Resources\FeatureFlagResource;
use App\Admin\Resources\FeedbackResource;
use App\Admin\Resources\KybResource;
use App\Admin\Resources\KycResource;
use App\Admin\Resources\NpsResource;
use App\Admin\Resources\PromoCodeResource;
use App\Admin\Resources\SiteResource;
use App\Admin\Resources\SmsMessageResource;
use App\Admin\Resources\TipResource;
use App\Admin\Resources\TradeResource;
use App\Admin\Resources\TranslationResource;
use App\Admin\Resources\UserResource;
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

            return $registry;
        });
    }
}
