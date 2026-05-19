<?php

namespace App\Services\GeolocationV2;

class GeocodingResult
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $formattedAddress = null,
        public readonly ?string $placeId = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $locality = null,
        public readonly array $components = [],
        public readonly string $provider = 'mock',
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'formatted_address' => $this->formattedAddress,
            'place_id' => $this->placeId,
            'country_code' => $this->countryCode,
            'postal_code' => $this->postalCode,
            'locality' => $this->locality,
            'components' => $this->components,
            'provider' => $this->provider,
        ];
    }
}
