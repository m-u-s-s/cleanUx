<?php

namespace App\Providers;

use App\Models\Sanctum\PersonalAccessTokenV2;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class ApiTokensV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ((bool) config('api_tokens_v2.enabled', true)) {
            Sanctum::usePersonalAccessTokenModel(PersonalAccessTokenV2::class);
        }
    }
}
