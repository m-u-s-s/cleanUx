<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    protected $model = CustomerProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'customer_type' => CustomerType::PERSONAL->value,
            'default_phone' => fake()->phoneNumber(),
            'default_address' => fake()->streetAddress(),
            'default_city' => fake()->city(),
            'default_postal_code' => fake()->postcode(),
            'default_country' => 'BE',
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
            'premium_started_at' => null,
            'premium_renewal_at' => null,
            'preferences' => null,
        ];
    }

    public function premium(): static
    {
        return $this->state([
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_started_at' => now()->subMonths(3),
            'premium_renewal_at' => now()->addMonths(9),
        ]);
    }

    public function company(): static
    {
        return $this->state(['customer_type' => CustomerType::COMPANY->value]);
    }
}
