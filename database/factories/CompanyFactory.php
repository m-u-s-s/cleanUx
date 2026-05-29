<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'tva_number' => 'BE'.fake()->numerify('##########'),
            'company_number' => fake()->numerify('##########'),
            'email' => fake()->companyEmail(),
            'phone' => '+32'.fake()->numerify('4########'),
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('####'),
            'city' => fake()->city(),
            'country' => 'BE',
            'lat' => fake()->latitude(49.5, 51.5),
            'lng' => fake()->longitude(2.5, 6.5),
            'google_place_id' => null,
            'stripe_connect_account_id' => null,
            'stripe_connect_status' => 'pending',
            'type' => fake()->randomElement(['cleaning', 'painting', 'babysitting']),
            'default_slot_duration' => 90,
            'max_daily_missions' => 8,
            'is_active' => true,
            'logo_path' => null,
            'primary_color' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
