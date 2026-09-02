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
use App\Models\User;
use App\Observers\BookingObserver;
use App\Observers\BookingPaymentDestinationObserver;
use App\Observers\BookingTipObserver;
use App\Observers\MissionTrackingPointObserver;
use App\Observers\QuestionRouteRulesObserver;
use App\Observers\RendezVousObserver;
use App\Observers\TradeTaxiRulesObserver;
use App\Observers\TripTrackingSessionObserver;
use App\Observers\UserObserver;
use App\Policies\CatalogPolicy;
use App\Policies\ChannelPolicy;
use App\Services\Assistant\Llm\AnthropicProvider;
use App\Services\Assistant\Llm\AnthropicStreamingProvider;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Calendar\Contracts\GoogleBusyFetcher;
use App\Services\Calendar\Fetchers\MockGoogleBusyFetcher;
use App\Services\Country\CountryConfigService;
use App\Services\FeatureFlag\FeatureFlagService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Payments\CommissionService;
use App\Services\Payments\StripeCountryMapper;
use App\Services\PeerRental\Partenaires\AssureurContract;
use App\Services\PeerRental\Partenaires\AssureurDeDemonstration;
use App\Services\PeerRental\Partenaires\TelematiqueContract;
use App\Services\PeerRental\Partenaires\TelematiqueDeDemonstration;
use App\Services\Tax\TaxCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public const MESSAGE_FILE_SYNCHRONE = 'QUEUE_CONNECTION=sync est refusé en production : les jobs '
        .'différés s’exécuteraient immédiatement, ce qui casse les vagues du moteur de répartition '
        .'(TTL de 20 s ignoré) et fait passer webhooks et courriels dans la requête HTTP. '
        .'Choisir redis, sqs ou database.';

    /** LA DÉCISION, ISOLÉE DE SON CONTEXTE — pour qu'elle soit mesurable. */
    public static function laFileSynchroneEstRefusee(string $environnement, string $pilote, bool $enConsole): bool
    {
        return $environnement === 'production' && $pilote === 'sync' && ! $enConsole;
    }

    /** Register any application services. */
    public function register(): void
    {
        app(MissionLifecycleService::class);

        // Phase 5 — Bind du provider LLM pour le chatbot.
        // Singleton car LlmClient (orchestrateur agentic) doit recevoir la même
        // instance HTTP-clientée durant un cycle de requête.
        $this->app->singleton(LlmProvider::class, AnthropicProvider::class);
        $this->app->singleton(AnthropicStreamingProvider::class);

        /*
         * LA LOCATION ENTRE MEMBRES — deux coquilles branchables.
         *
         * Ni l'assureur ni le boitier telematique n'ont de partenaire contractualise. Les
         * implementations de demonstration repondent au contrat et disent, par
         * `estOperationnel()`, qu'elles ne couvrent ni ne deverrouillent rien. Le jour d'un
         * vrai contrat, c'est le pilote dans `config/peer_rental.php` qui change, pas le code.
         */
        $this->app->bind(AssureurContract::class, fn () => match ((string) config('peer_rental.insurance.driver', 'demo')) {
            default => new AssureurDeDemonstration,
        });

        $this->app->bind(TelematiqueContract::class, fn () => match ((string) config('peer_rental.telematics.driver', 'demo')) {
            default => new TelematiqueDeDemonstration,
        });

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
        // Feature flags — singleton, config-driven, no I/O
        $this->app->singleton(FeatureFlagService::class);
    }

    /** Bootstrap any application services. */
    public function boot(): void
    {
        // ÉCARTER UN ATTRIBUT EN SILENCE, C'EST PERDRE UNE DONNÉE SANS LE DIRE.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventLazyLoading(! app()->isProduction());

        Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
            $class = get_class($model);
            logger()->warning("N+1 lazy load: {$class}.{$relation}");
        });

        $this->refuserLaFileSynchroneEnProduction();
        $this->refuserLesJetonsDesComptesSuspendus();

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
        // Changer de numéro perd la vérification du numéro — voir `UserObserver`, et le pendant
        // e-mail qui faisait déjà cela dans `UpdateUserProfileInformation`.
        User::observe(UserObserver::class);
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

        // Un métier devient — ou cesse d'être — un service de trajet quand ses questions de localisation changent.
        Question::observe(QuestionRouteRulesObserver::class);

        // Même raison, autre colonne : « règles taxi » s'écrit depuis le web, depuis la console
        // mobile et depuis les seeders. La date de bascule suit la colonne, pas ses écrivains.
        Trade::observe(TradeTaxiRulesObserver::class);

        Gate::policy(Channel::class, ChannelPolicy::class);

        // Le catalogue de commande : UNE règle d'écriture, cinq modèles.
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

    /** EN PRODUCTION, UNE FILE « sync » N'EST PAS UNE FILE — C'EST UNE PANNE SILENCIEUSE. */
    private function refuserLaFileSynchroneEnProduction(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (config('queue.default') !== 'sync') {
            return;
        }

        // ON LAISSE PASSER LA CONSOLE, ET C'EST ESSENTIEL.
        if (app()->runningInConsole()) {
            return;
        }

        throw new RuntimeException(self::MESSAGE_FILE_SYNCHRONE);
    }

    /** UN COMPTE SUSPENDU N'A PLUS DE JETON VALIDE — sur toutes les routes, y compris futures. */
    private function refuserLesJetonsDesComptesSuspendus(): void
    {
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $estValide): bool {
            if (! $estValide) {
                return false;
            }

            $porteur = $accessToken->tokenable;

            // Un porteur qui n'est pas un utilisateur (jeton de service) ne relève pas de cette
            // règle : on ne lui invente pas un état de compte qu'il n'a pas.
            if (! $porteur instanceof User) {
                return true;
            }

            return $porteur->compteActif();
        });
    }
}
