<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSite>
 */
class OrganizationSiteFactory extends Factory
{
    protected $model = OrganizationSite::class;

    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'client_user_id' => User::factory()->client(),
            'service_zone_id' => ServiceZone::factory(),
            'postal_code_id' => PostalCode::factory(),
            'name' => fake()->company().' - '.fake()->city(),
            'site_code' => strtoupper(fake()->unique()->bothify('SITE-###')),
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'access_instructions' => fake()->optional()->sentence(),
            'latitude' => fake()->latitude(49.4, 51.6),
            'longitude' => fake()->longitude(2.5, 6.4),
            'is_primary' => false,
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
