<?php

namespace App\Providers;

use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\Providers\HiscoxInsuranceProvider;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use App\Services\Insurance\Providers\WakamInsuranceProvider;
use Illuminate\Support\ServiceProvider;

class InsuranceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InsuranceProviderInterface::class, function ($app) {
            $name = (string) config('insurance.default_provider', 'mock');
            return match ($name) {
                'hiscox' => new HiscoxInsuranceProvider(),
                'wakam'  => new WakamInsuranceProvider(),
                default  => new InsuranceMockProvider(),
            };
        });
    }
}
