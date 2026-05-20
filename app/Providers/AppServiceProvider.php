<?php

namespace App\Providers;

use App\Models\Channel;
use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Observers\RendezVousObserver;
use App\Policies\ChannelPolicy;
use App\Services\Assistant\Llm\AnthropicProvider;
use App\Services\Assistant\Llm\LlmProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        app(\App\Services\Missions\MissionLifecycleService::class);

        // Phase 5 — Bind du provider LLM pour le chatbot.
        // Singleton car LlmClient (orchestrateur agentic) doit recevoir la même
        // instance HTTP-clientée durant un cycle de requête.
        $this->app->singleton(LlmProvider::class, AnthropicProvider::class);
        $this->app->singleton(\App\Services\Assistant\Llm\AnthropicStreamingProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        \Illuminate\Database\Eloquent\Builder::macro('clientFacing', function () {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            $model = $this->getModel();
            $table = $model->getTable();

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'is_active')) {
                $this->where($table . '.is_active', true);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'is_visible')) {
                $this->where($table . '.is_visible', true);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'client_facing')) {
                $this->where($table . '.client_facing', true);
            }

            return $this;
        });


        \Carbon\Carbon::setLocale('fr');
        Booking::observe(RendezVousObserver::class);
        Booking::observe(BookingObserver::class);

        // Tips v2 — push provider on tip charged/paid_out
        if (class_exists(\App\Models\BookingTip::class) && class_exists(\App\Observers\BookingTipObserver::class)) {
            \App\Models\BookingTip::observe(\App\Observers\BookingTipObserver::class);
        }

        // Trip Tracking v2 — push client on enroute/arrived/in_mission transitions
        if (class_exists(\App\Models\TripTrackingSession::class) && class_exists(\App\Observers\TripTrackingSessionObserver::class)) {
            \App\Models\TripTrackingSession::observe(\App\Observers\TripTrackingSessionObserver::class);
        }

        // MissionTrackingPoint → MissionEtaUpdated broadcast (was unwired)
        if (class_exists(\App\Models\MissionTrackingPoint::class) && class_exists(\App\Observers\MissionTrackingPointObserver::class)) {
            \App\Models\MissionTrackingPoint::observe(\App\Observers\MissionTrackingPointObserver::class);
        }

        Gate::policy(Channel::class, ChannelPolicy::class);
    }
}
