<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\RendezVous;
use App\Observers\RendezVousObserver;
use App\Services\Assistant\Llm\AnthropicProvider;
use App\Services\Assistant\Llm\LlmProvider;

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
    }
}
