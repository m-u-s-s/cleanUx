<?php

namespace Database\Factories;

use App\Models\BusinessBeneficialOwner;
use App\Models\BusinessEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessBeneficialOwnerFactory extends Factory
{
    protected $model = BusinessBeneficialOwner::class;

    public function definition(): array
    {
        return [
            'entity_id' => fn () => BusinessEntity::factory()->create()->id,
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'country_of_residence' => 'BE',
            'nationality' => 'BE',
            'ownership_percent' => fake()->randomFloat(2, 10, 100),
            'is_director' => fake()->boolean(),
            'is_pep' => false,
            'is_sanctioned' => false,
            'aml_status' => BusinessBeneficialOwner::AML_PENDING,
            'metadata' => null,
        ];
    }

    public function flagged(): static
    {
        return $this->state(fn () => [
            'is_pep' => true,
            'aml_status' => BusinessBeneficialOwner::AML_FLAGGED,
        ]);
    }
}
