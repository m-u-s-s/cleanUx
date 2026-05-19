<?php

namespace App\Providers;

use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\Providers\KycMockProvider;
use App\Services\Kyc\Providers\OnfidoProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class KycServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KycProviderInterface::class, function ($app) {
            return $this->resolveProvider();
        });
    }

    public function boot(): void
    {
    }

    protected function resolveProvider(): KycProviderInterface
    {
        $default = (string) config('kyc.default_provider', 'mock');

        return match ($default) {
            'onfido' => new OnfidoProvider(),
            'mock' => new KycMockProvider(),
            default => throw new RuntimeException("KYC provider not implemented: {$default}"),
        };
    }
}
