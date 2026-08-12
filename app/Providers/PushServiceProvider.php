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
        /*
         * LE CANAL `sms` EST ENREGISTRÉ ICI — il ne l'était nulle part.
         *
         * `SmsChannel` était écrit, complet, avec sa résolution de numéro, son idempotence et sa
         * catégorie… et n'avait ZÉRO référence dans tout le dépôt. Laravel ne devine pas les canaux
         * maison : une notification qui déclare `via() = ['sms']` sans cet appel lève
         * « Driver [sms] not supported ». Le module SMS entier — pilotes Twilio et bouchon,
         * registre d'envois, plafonnement par numéro — attendait cette ligne pour servir à quelque
         * chose.
         */
        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));
    }
}
