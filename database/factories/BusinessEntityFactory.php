<?php

namespace Database\Factories;

use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessEntityFactory extends Factory
{
    protected $model = BusinessEntity::class;

    public function definition(): array
    {
        return [
            'code'               => BusinessEntity::generateCode(),
            'legal_name'         => fake()->company() . ' SRL',
            'trade_name'         => fake()->optional()->company(),
            'country_code'       => fake()->randomElement(['BE', 'FR', 'NL']),
            'identifier_type'    => 'vat',
            'identifier_value'   => 'BE' . fake()->numerify('##########'),
            'vat_id'             => 'BE' . fake()->numerify('##########'),
            'legal_form'         => fake()->randomElement(['SRL', 'SA', 'SNC', 'BV']),
            'registered_address' => [
                'street' => fake()->streetAddress(),
                'city'   => fake()->city(),
                'zip'    => fake()->postcode(),
            ],
            'incorporation_date' => fake()->dateTimeBetween('-20 years', '-1 year')->format('Y-m-d'),
            'status'             => BusinessEntity::STATUS_PENDING,
            'risk_score'         => 0.0,
            'risk_level'         => BusinessEntity::RISK_LOW,
            'owner_user_id'      => User::factory(),
            'contact_user_id'    => null,
            'contact_email'      => fake()->companyEmail(),
            'verified_at'        => null,
            'verified_by_user_id' => null,
            'rejected_at'        => null,
            'rejection_reason'   => null,
            'metadata'           => [],
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status'      => BusinessEntity::STATUS_VERIFIED,
            'verified_at' => now()->subDays(10),
        ]);
    }

    public function highRisk(): static
    {
        return $this->state(fn () => [
            'risk_score' => 75.0,
            'risk_level' => BusinessEntity::RISK_HIGH,
        ]);
    }
}
