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
        \Carbon\Carbon::setLocale('fr');
        Booking::observe(RendezVousObserver::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
    }
}
