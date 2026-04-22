<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        do {
            $iso2 = strtoupper(fake()->lexify('??'));
        } while (
            in_array($iso2, ['BE'], true)
            || Country::query()->where('iso_code', $iso2)->exists()
        );

        do {
            $iso3 = strtoupper($iso2 . fake()->randomLetter());
        } while (
            in_array($iso3, ['BEL'], true)
            || Country::query()->where('iso3_code', $iso3)->exists()
        );

        return [
            'iso_code' => $iso2,
            'iso3_code' => $iso3,
            'name' => 'Pays ' . $iso2,
            'official_name' => 'Pays ' . $iso2,
            'default_locale' => 'fr_BE',
            'currency_code' => 'EUR',
            'phone_code' => '+' . fake()->numberBetween(100, 999),
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ];
    }

    public function belgium(): static
    {
        return $this->state(fn () => [
            'iso_code' => 'BE',
            'iso3_code' => 'BEL',
            'name' => 'Belgique',
            'official_name' => 'Royaume de Belgique',
            'default_locale' => 'fr_BE',
            'currency_code' => 'EUR',
            'phone_code' => '+32',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);
    }
}
