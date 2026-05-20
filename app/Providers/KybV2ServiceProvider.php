<?php

namespace App\Providers;

use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Services\KybV2\Contracts\SanctionsScreeningProviderContract;
use App\Services\KybV2\Providers\CompaniesHouseBusinessVerificationProvider;
use App\Services\KybV2\Providers\InseeBusinessVerificationProvider;
use App\Services\KybV2\Providers\MockBusinessVerificationProvider;
use App\Services\KybV2\Providers\MockSanctionsScreeningProvider;
use App\Services\KybV2\Providers\ViesVatVerificationProvider;
use Illuminate\Support\ServiceProvider;

class KybV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BusinessVerificationProviderContract::class, function () {
            return match ((string) config('kyb_v2.identity_provider', 'mock')) {
                'insee' => new InseeBusinessVerificationProvider(),
                'companies_house' => new CompaniesHouseBusinessVerificationProvider(),
                'vies' => new ViesVatVerificationProvider(),
                default => new MockBusinessVerificationProvider(),
            };
        });

        $this->app->singleton(SanctionsScreeningProviderContract::class, function () {
            return match ((string) config('kyb_v2.sanctions_provider', 'mock')) {
                default => new MockSanctionsScreeningProvider(),
            };
        });
    }
}
