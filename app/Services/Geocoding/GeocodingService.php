<?php

namespace App\Services\Geocoding;

use App\Models\LocationGeocode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeocodingService
{
    public function resolve(?string $addressLine, ?string $postalCode, ?string $city, ?string $countryCode = 'BE'): ?array
    {
        $addressLine = $this->clean($addressLine);
        $postalCode = $this->clean($postalCode);
        $city = $this->clean($city);
        $countryCode = strtoupper($this->clean($countryCode) ?: 'BE');

        if (! $addressLine && ! $postalCode && ! $city) {
            return null;
        }

        $lookupHash = sha1(json_encode([
            'address_line' => $addressLine,
            'postal_code' => $postalCode,
            'city' => $city,
            'country_code' => $countryCode,
        ]));

        $cached = LocationGeocode::query()->where('lookup_hash', $lookupHash)->first();

        if ($cached) {
            return [
                'lat' => (float) $cached->lat,
                'lng' => (float) $cached->lng,
                'provider' => $cached->provider,
                'cached' => true,
            ];
        }

        $query = trim(collect([$addressLine, $postalCode, $city, $countryCode])->filter()->implode(', '));

        $response = Http::timeout(12)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => config('app.name', 'CleanUx').'/1.0 geocoding',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 1,
                'addressdetails' => 1,
            ]);

        if (! $response->ok()) {
            return null;
        }

        $result = $response->json();

        if (! is_array($result) || empty($result[0]['lat']) || empty($result[0]['lon'])) {
            return null;
        }

        $lat = (float) $result[0]['lat'];
        $lng = (float) $result[0]['lon'];

        LocationGeocode::query()->create([
            'lookup_hash' => $lookupHash,
            'address_line' => $addressLine,
            'postal_code' => $postalCode,
            'city' => $city,
            'country_code' => $countryCode,
            'lat' => $lat,
            'lng' => $lng,
            'provider' => 'nominatim',
            'raw' => $result[0],
        ]);

        return [
            'lat' => $lat,
            'lng' => $lng,
            'provider' => 'nominatim',
            'cached' => false,
        ];
    }

    protected function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? Str::of($value)->squish()->toString() : null;
    }
}