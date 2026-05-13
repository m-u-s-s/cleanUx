<?php

namespace App\Providers;

use App\Models\Channel;
use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
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
        Gate::policy(Channel::class, ChannelPolicy::class);
    }
}
