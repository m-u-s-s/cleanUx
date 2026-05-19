<?php

namespace App\Providers;

use App\Services\SubscriptionsV2\Contracts\BillingProviderContract;
use App\Services\SubscriptionsV2\Providers\MockBillingProvider;
use App\Services\SubscriptionsV2\Providers\StripeBillingProvider;
use Illuminate\Support\ServiceProvider;

class SubscriptionsV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillingProviderContract::class, function () {
            $driver = (string) config('subscriptions_v2.billing_driver', 'mock');
            return match ($driver) {
                'stripe' => new StripeBillingProvider(),
                default => new MockBillingProvider(),
            };
        });
    }
}
