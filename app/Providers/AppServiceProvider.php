<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\Channel;
use App\Models\MissionTrackingPoint;
use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\TripTrackingSession;
use App\Observers\BookingObserver;
use App\Observers\BookingPaymentDestinationObserver;
use App\Observers\BookingTipObserver;
use App\Observers\MissionTrackingPointObserver;
use App\Observers\RendezVousObserver;
use App\Observers\TripTrackingSessionObserver;
use App\Policies\CatalogPolicy;
use App\Policies\ChannelPolicy;
use App\Services\Assistant\Llm\AnthropicProvider;
use App\Services\Assistant\Llm\AnthropicStreamingProvider;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Calendar\Contracts\GoogleBusyFetcher;
use App\Services\Calendar\Fetchers\MockGoogleBusyFetcher;
use App\Services\Country\CountryConfigService;
use App\Services\Dispatch\MatchingScorer;
use App\Services\FeatureFlag\FeatureFlagService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Payments\CommissionService;
use App\Services\Payments\StripeCountryMapper;
use App\Services\Tax\TaxCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        app(MissionLifecycleService::class);

        // Phase 5 — Bind du provider LLM pour le chatbot.
        // Singleton car LlmClient (orchestrateur agentic) doit recevoir la même
        // instance HTTP-clientée durant un cycle de requête.
        $this->app->singleton(LlmProvider::class, AnthropicProvider::class);
        $this->app->singleton(AnthropicStreamingProvider::class);

        // Feature flags — singleton so the DB-override lookup is memoised per request (M18).
        $this->app->singleton(FeatureFlagService::class);

        // Monetisation — singletons for stateless calculators
        $this->app->singleton(CommissionService::class);
        $this->app->singleton(StripeCountryMapper::class);
        $this->app->singleton(TaxCalculator::class);

        // GCal bidirectionnel — fetcher Mock par défaut (le client Google API réel
        // sera lié ici quand l'intégration API sera activée).
        $this->app->singleton(
            GoogleBusyFetcher::class,
            MockGoogleBusyFetcher::class,
        );

        // Multi-country config — singleton, pure data (no I/O)
        $this->app->singleton(CountryConfigService::class);

        // Dispatch — scoring engine singleton (stateless, thread-safe)
        $this->app->singleton(MatchingScorer::class);

        // Feature flags — singleton, config-driven, no I/O
        $this->app->singleton(FeatureFlagService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
            $class = get_class($model);
            logger()->warning("N+1 lazy load: {$class}.{$relation}");
        });

        Builder::macro('clientFacing', function () {
            /** @var Builder $this */
            $model = $this->getModel();
            $table = $model->getTable();

            if (Schema::hasColumn($table, 'is_active')) {
                $this->where($table.'.is_active', true);
            }

            if (Schema::hasColumn($table, 'is_visible')) {
                $this->where($table.'.is_visible', true);
            }

            if (Schema::hasColumn($table, 'client_facing')) {
                $this->where($table.'.client_facing', true);
            }

            return $this;
        });

        Carbon::setLocale('fr');
        Booking::observe(RendezVousObserver::class);
        Booking::observe(BookingObserver::class);
        // Garde d'argent : une retenue bancaire désigne un compte prestataire précis.
        Booking::observe(BookingPaymentDestinationObserver::class);

        // Tips v2 — push provider on tip charged/paid_out
        if (class_exists(BookingTip::class) && class_exists(BookingTipObserver::class)) {
            BookingTip::observe(BookingTipObserver::class);
        }

        // Trip Tracking v2 — push client on enroute/arrived/in_mission transitions
        if (class_exists(TripTrackingSession::class) && class_exists(TripTrackingSessionObserver::class)) {
            TripTrackingSession::observe(TripTrackingSessionObserver::class);
        }

        // MissionTrackingPoint → MissionEtaUpdated broadcast (was unwired)
        if (class_exists(MissionTrackingPoint::class) && class_exists(MissionTrackingPointObserver::class)) {
            MissionTrackingPoint::observe(MissionTrackingPointObserver::class);
        }

        Gate::policy(Channel::class, ChannelPolicy::class);

        /*
         * Le catalogue de commande : UNE règle d'écriture, cinq modèles.
         *
         * Elle vivait en trois exemplaires — middleware de route, trait d'écran, garde recopiée
         * dans deux composants. Trois copies finissent par diverger, et c'est alors la plus
         * permissive qui décide sans que personne ne le remarque.
         */
        foreach ([
            Sector::class,
            Trade::class,
            Question::class,
            QuestionOption::class,
            QuestionCondition::class,
        ] as $catalogModel) {
            Gate::policy($catalogModel, CatalogPolicy::class);
        }

        // Feature flags — Blade directive: @feature('flag') / @endfeature
        Blade::if('feature', function (string $flag): bool {
            return app(FeatureFlagService::class)->isEnabled($flag, auth()->user());
        });

        // M3 — @mediaUrl($path): signed URL to the authenticated private-media route. Use for
        // mission/dispute photos instead of asset('storage/'.$path).
        Blade::directive('mediaUrl', function (string $expr): string {
            return "<?php echo e(\\App\\Support\\Media\\PrivateMedia::url({$expr})); ?>";
        });
    }
}
