<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\FieldTeam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FieldTeam>
 */
class FieldTeamFactory extends Factory
{
    protected $model = FieldTeam::class;

    public function definition(): array
    {
        $name = fake()->company().' Team';

        return [
            'country_id' => Country::factory(),
            'service_zone_id' => null,
            'organization_account_id' => null,
            'service_partner_id' => null,
            'team_lead_user_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'status' => 'active',
            'is_internal' => true,
            'max_concurrent_missions' => fake()->numberBetween(3, 10),
            'metadata' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
