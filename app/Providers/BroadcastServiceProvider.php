<?php

namespace App\Providers;

use App\Broadcasting\TestingBroadcaster;
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
            return new TestingBroadcaster;
        });

        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
