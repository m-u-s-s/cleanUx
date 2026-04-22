<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\OrganizationAccount;
use App\Models\PostalCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationAccount>
 */
class OrganizationAccountFactory extends Factory
{
    protected $model = OrganizationAccount::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'country_id' => Country::factory(),
            'region_id' => null,
            'province_id' => null,
            'commune_id' => null,
            'postal_code_id' => PostalCode::factory(),
            'name' => $name,
            'legal_name' => $name.' SRL',
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'type' => fake()->randomElement(['business', 'entreprise', 'partner']),
            'tva_number' => 'BE'.fake()->unique()->numerify('0#########'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'billing_email' => fake()->companyEmail(),
            'status' => fake()->randomElement(['active', 'prospect', 'inactive']),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'is_multisite' => fake()->boolean(),
            'is_key_account' => fake()->boolean(20),
            'metadata' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
