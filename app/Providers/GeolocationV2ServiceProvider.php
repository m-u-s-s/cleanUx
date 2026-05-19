<?php

namespace App\Providers;

use App\Services\GeolocationV2\Contracts\GeocodingProviderContract;
use App\Services\GeolocationV2\Providers\GoogleGeocodingProvider;
use App\Services\GeolocationV2\Providers\MapboxGeocodingProvider;
use App\Services\GeolocationV2\Providers\MockGeocodingProvider;
use Illuminate\Support\ServiceProvider;

class GeolocationV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeocodingProviderContract::class, function ($app) {
            $name = (string) config('geolocation_v2.provider', 'mock');
            return match ($name) {
                'google' => new GoogleGeocodingProvider(),
                'mapbox' => new MapboxGeocodingProvider(),
                default => new MockGeocodingProvider(),
            };
        });
    }
}
