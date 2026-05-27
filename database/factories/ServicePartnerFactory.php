<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\ServicePartner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServicePartnerFactory extends Factory
{
    protected $model = ServicePartner::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'country_id'    => fn () => Country::factory()->create()->id,
            'name'          => $name,
            'legal_name'    => $name . ' SPRL',
            'slug'          => Str::slug($name) . '-' . Str::random(4),
            'status'        => 'active',
            'email'         => fake()->companyEmail(),
            'phone'         => '+32' . fake()->numerify('4########'),
            'billing_email' => fake()->companyEmail(),
            'quality_score' => fake()->randomFloat(2, 3.0, 5.0),
            'is_active'     => true,
            'metadata'      => null,
            'notes'         => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false, 'status' => 'inactive']);
    }
}
