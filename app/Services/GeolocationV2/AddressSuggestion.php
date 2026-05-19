<?php

namespace App\Services\GeolocationV2;

class AddressSuggestion
{
    public function __construct(
        public readonly string $description,
        public readonly ?string $placeId = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $mainText = null,
        public readonly ?string $secondaryText = null,
        public readonly ?string $postalCode = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly string $provider = 'mock',
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'place_id' => $this->placeId,
            'country_code' => $this->countryCode,
            'main_text' => $this->mainText,
            'secondary_text' => $this->secondaryText,
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'provider' => $this->provider,
        ];
    }
}
