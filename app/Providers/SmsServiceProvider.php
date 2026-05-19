<?php

namespace App\Providers;

use App\Services\Sms\Providers\SmsMockProvider;
use App\Services\Sms\Providers\TwilioSmsProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsProviderInterface::class, function ($app) {
            $default = (string) config('sms.default_provider', 'mock');

            return match ($default) {
                'twilio' => new TwilioSmsProvider(),
                'vonage' => throw new RuntimeException('Vonage SMS provider not yet implemented.'),
                'mock' => new SmsMockProvider(),
                default => throw new RuntimeException("SMS provider not implemented: {$default}"),
            };
        });
    }
}
