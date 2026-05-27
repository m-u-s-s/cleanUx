<?php

namespace Database\Factories;

use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingSegmentMemberFactory extends Factory
{
    protected $model = MarketingSegmentMember::class;

    public function definition(): array
    {
        return [
            'segment_id' => fn () => MarketingSegment::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'computed_at' => now(),
            'score' => fake()->randomFloat(4, 0, 1),
            'metadata' => null,
        ];
    }
}
