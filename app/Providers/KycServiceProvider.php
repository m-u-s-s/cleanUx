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

    public function boot(): void {}

    protected function resolveProvider(): KycProviderInterface
    {
        // Auto-detect: if an Onfido API token is present in the environment,
        // use Onfido regardless of KYC_PROVIDER value (graceful upgrade path).
        // Override by setting KYC_PROVIDER=mock explicitly to force mock even
        // when a token is present (useful in CI / staging).
        $configured = (string) config('kyc.default_provider', 'mock');
        $onfidoToken = (string) config('kyc.providers.onfido.api_token', '');

        $resolved = $configured;
        if ($resolved === 'mock' && $onfidoToken !== '') {
            // Token is present but config still says 'mock' → upgrade to onfido.
            $resolved = 'onfido';
        }

        return match ($resolved) {
            'onfido' => new OnfidoProvider,
            'mock' => new KycMockProvider,
            default => throw new RuntimeException("KYC provider not implemented: {$resolved}"),
        };
    }
}
