<?php

namespace App\Providers;

use App\Services\TenancyV2\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }
}
