<?php

namespace App\Services\GeolocationV2\Contracts;

use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\DistanceResult;
use App\Services\GeolocationV2\GeocodingResult;

interface GeocodingProviderContract
{
    public function name(): string;

    /**
     * @return AddressSuggestion[]
     */
    public function autocomplete(string $query, ?string $countryCode = null, int $limit = 8): array;

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult;

    public function reverseGeocode(float $latitude, float $longitude): ?GeocodingResult;

    public function distance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode = 'driving',
    ): ?DistanceResult;
}
