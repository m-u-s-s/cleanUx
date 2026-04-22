<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\RendezVous;
use App\Observers\RendezVousObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        app(\App\Services\Missions\MissionLifecycleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RendezVous::observe(RendezVousObserver::class);
    }
}
