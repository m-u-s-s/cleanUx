<?php

namespace App\Providers;

use App\Models\Channel;
use Illuminate\Support\ServiceProvider;
use App\Models\RendezVous;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RendezVous::observe(RendezVousObserver::class);
        // Phase 4.1 — Channel moderation policy
        Gate::policy(Channel::class, ChannelPolicy::class);
    }
}
