<?php

namespace App\Providers;

use App\Services\Fx\FxProviderInterface;
use App\Services\Fx\Providers\EcbFxProvider;
use App\Services\Fx\Providers\FxMockProvider;
use App\Services\Fx\Providers\OpenExchangeRatesFxProvider;
use Illuminate\Support\ServiceProvider;

class FxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FxProviderInterface::class, function ($app) {
            $name = (string) config('fx.default_provider', 'mock');
            return match ($name) {
                'ecb' => new EcbFxProvider(),
                'openexchange' => new OpenExchangeRatesFxProvider(),
                default => new FxMockProvider(),
            };
        });
    }
}
