<?php

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Services\Push\Providers\ApnsPushProvider;
use App\Services\Push\Providers\FcmPushProvider;
use App\Services\Push\Providers\PushMockProvider;
use App\Services\Push\PushProviderInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class PushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushProviderInterface::class, function ($app) {
            $name = (string) config('push.default_provider', 'mock');

            return match ($name) {
                'fcm' => new FcmPushProvider,
                'apns' => new ApnsPushProvider,
                default => new PushMockProvider,
            };
        });
    }

    public function boot(): void
    {
        // LE CANAL `sms` EST ENREGISTRÉ ICI — il ne l'était nulle part.
        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));
    }
}
