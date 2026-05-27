<?php

namespace Database\Factories;

use App\Models\MarketingSegment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketingSegment>
 */
class MarketingSegmentFactory extends Factory
{
    protected $model = MarketingSegment::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'code'              => Str::slug($name),
            'name'              => ucwords($name),
            'description'       => fake()->sentence(),
            'rules'             => [
                ['field' => 'role', 'operator' => 'eq', 'value' => 'client'],
            ],
            'is_active'         => true,
            'member_count'      => 0,
            'last_computed_at'  => null,
            'created_by_user_id' => null,
            'metadata'          => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
