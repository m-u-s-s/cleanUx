<?php

namespace Database\Factories;

use App\Models\LocationGeocode;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationGeocodeFactory extends Factory
{
    protected $model = LocationGeocode::class;

    public function definition(): array
    {
        $postalCode  = fake()->numerify('1###');
        $city        = fake()->city();
        $addressLine = fake()->streetAddress() . ', ' . $postalCode . ' ' . $city;
        $hash        = sha1($addressLine . '|' . $postalCode . '|' . $city);

        return [
            'address'      => $addressLine,
            'address_line' => $addressLine,
            'address_hash' => $hash,
            'lookup_hash'  => $hash,
            'postal_code'  => $postalCode,
            'city'         => $city,
            'country_code' => 'BE',
            'lat'          => fake()->latitude(49.5, 51.5),
            'lng'          => fake()->longitude(2.5, 6.5),
            'provider'     => 'mock',
            'raw'          => null,
        ];
    }
}
