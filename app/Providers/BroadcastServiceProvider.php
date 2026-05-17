<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::extend('testing', function ($app) {
            return new \App\Broadcasting\TestingBroadcaster();
        });

        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
