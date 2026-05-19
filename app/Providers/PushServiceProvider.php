<?php

namespace App\Providers;

use App\Services\Push\Providers\ApnsPushProvider;
use App\Services\Push\Providers\FcmPushProvider;
use App\Services\Push\Providers\PushMockProvider;
use App\Services\Push\PushProviderInterface;
use Illuminate\Support\ServiceProvider;

class PushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushProviderInterface::class, function ($app) {
            $name = (string) config('push.default_provider', 'mock');

            return match ($name) {
                'fcm' => new FcmPushProvider(),
                'apns' => new ApnsPushProvider(),
                default => new PushMockProvider(),
            };
        });
    }

    public function boot(): void
    {
        // No-op; channel binding is handled by config/services or Notification::extend at runtime.
    }
}
