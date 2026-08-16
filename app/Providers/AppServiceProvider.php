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
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public const MESSAGE_FILE_SYNCHRONE = 'QUEUE_CONNECTION=sync est refusé en production : les jobs '
        .'différés s’exécuteraient immédiatement, ce qui casse les vagues du moteur de répartition '
        .'(TTL de 20 s ignoré) et fait passer webhooks et courriels dans la requête HTTP. '
        .'Choisir redis, sqs ou database.';

    /**
     * LA DÉCISION, ISOLÉE DE SON CONTEXTE — pour qu'elle soit mesurable.
     *
     * `runningInConsole()` est figé au démarrage du conteneur : sous PHPUnit il vaut TOUJOURS vrai.
     * Un test qui prétendrait simuler une requête HTTP passerait donc pour une mauvaise raison.
     * On sort les deux entrées — l'environnement et le contexte — en paramètres : la règle devient
     * vérifiable telle qu'elle est écrite, et non telle qu'on aimerait la lire.
     */
    public static function laFileSynchroneEstRefusee(string $environnement, string $pilote, bool $enConsole): bool
    {
        return $environnement === 'production' && $pilote === 'sync' && ! $enConsole;
    }

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
        /*
         * ÉCARTER UN ATTRIBUT EN SILENCE, C'EST PERDRE UNE DONNÉE SANS LE DIRE.
         *
         * Par défaut, Eloquent jette sans un mot tout attribut absent de `$fillable`. Ce dépôt en
         * payait le prix à onze endroits au moins : le contexte de présence des prestataires, le
         * chemin du rapport de mission, le canal d'origine des réservations, sept colonnes des
         * sites d'entreprise — tous écrits par du code de production, tous perdus. Aucune erreur,
         * aucune alerte, aucun test rouge : la ligne s'enregistrait simplement incomplète.
         *
         * Le refus n'est PAS activé en production, et c'est délibéré. Là-bas, un appel oublié
         * lèverait une exception au milieu d'un paiement ou d'une clôture de mission ; ici, il
         * s'arrête net devant la personne qui peut le corriger.
         */
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

        /*
         * Un métier devient — ou cesse d'être — un service de trajet quand ses questions de
         * localisation changent. La DATE de cette bascule fait courir le délai laissé aux
         * prestataires déjà inscrits : elle suit donc la table qui la décide, et non les trois
         * écrans qui l'écrivent.
         */
        Question::observe(QuestionRouteRulesObserver::class);

        // Même raison, autre colonne : « règles taxi » s'écrit depuis le web, depuis la console
        // mobile et depuis les seeders. La date de bascule suit la colonne, pas ses écrivains.
        Trade::observe(TradeTaxiRulesObserver::class);

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

    /**
     * EN PRODUCTION, UNE FILE « sync » N'EST PAS UNE FILE — C'EST UNE PANNE SILENCIEUSE.
     *
     * `config/queue.php` a pour défaut `sync`, et `.env.example` le reprend. Avec ce pilote, un job
     * mis en file s'exécute IMMÉDIATEMENT, dans le processus appelant. Deux conséquences que rien ne
     * signale :
     *
     *  - `EscalateMissionAssignmentJob` est dispatché avec `->delay($assignment->expires_at)`. En
     *    `sync`, le délai est IGNORÉ : l'escalade part à l'instant même. Le TTL de 20 secondes et
     *    les vagues du moteur de répartition n'existent plus — la première offre est écrasée avant
     *    que le prestataire ait vu sa notification.
     *  - Chaque webhook Stripe, chaque envoi de courriel, chaque géocodage se fait dans la requête
     *    HTTP. Une lenteur de Stripe devient une lenteur de la page.
     *
     * POURQUOI REFUSER LE BOOT PLUTÔT QUE JOURNALISER. `config:parity-check` garde déjà le
     * DÉPLOIEMENT (lot 0), mais quelqu'un peut éditer `.env` sur le serveur sans redéployer. Un
     * avertissement dans les journaux ne serait lu par personne — c'est précisément ainsi que ce
     * réglage a survécu jusqu'ici. Une application qui refuse de démarrer se remarque en une minute ;
     * des vagues de répartition cassées se remarquent en un mois, à travers des clients perdus.
     *
     * Hors production, on ne dit rien : `sync` est le bon réglage pour développer et pour tester.
     */
    private function refuserLaFileSynchroneEnProduction(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (config('queue.default') !== 'sync') {
            return;
        }

        /*
         * ON LAISSE PASSER LA CONSOLE, ET C'EST ESSENTIEL.
         *
         * Lever depuis boot() bloque TOUTE commande artisan — y compris `config:clear`, qui est
         * précisément l'outil avec lequel on répare une configuration fautive. Une garde qui
         * empêche de réparer ce qu'elle signale enferme l'exploitant dehors : il lui resterait à
         * éditer .env à la main puis à espérer, sans pouvoir vider le cache.
         *
         * Le déploiement reste couvert autrement, et mieux : `config:parity-check` refuse `sync`
         * AVANT la migration, avec un rapport lisible qui nomme le réglage fautif. Ici on garde le
         * trafic HTTP et les workers, c'est-à-dire ce qui sert réellement les clients.
         */
        if (app()->runningInConsole()) {
            return;
        }

        throw new RuntimeException(self::MESSAGE_FILE_SYNCHRONE);
    }

    /**
     * UN COMPTE SUSPENDU N'A PLUS DE JETON VALIDE — sur toutes les routes, y compris futures.
     *
     * Mesuré le 2026-08-16 : `active.account` était posé sur chaque groupe web et sur AUCUNE route
     * d'API. Un compte `is_active=false` obtenait un jeton neuf par `/api/auth/login` et gardait
     * l'application entière — offres, réservations, disponibilités. La suspension ne valait que
     * dans le navigateur.
     *
     * POURQUOI ICI ET PAS EN MIDDLEWARE SUR CHAQUE GROUPE. Les routes d'API sont réparties sur une
     * douzaine de groupes `auth:sanctum` dans huit fichiers ; en ajouter un par groupe, c'est
     * accepter qu'on en oublie un — et le groupe oublié est exactement celui qu'on ne testera pas.
     * Ce point de passage est celui que Sanctum traverse pour CHAQUE requête portant un jeton :
     * la règle vaut donc aussi pour la route écrite demain.
     *
     * Le jeton devient invalide, donc la réponse est 401 « Unauthenticated » et non 403. C'est le
     * bon signal pour un client mobile : il déconnecte et renvoie à l'écran de connexion, où le 403
     * explicite de `ApiAuthController::login` nomme la raison. Un 403 en cours de session laisserait
     * l'application afficher des écrans vides sans savoir pourquoi.
     */
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
