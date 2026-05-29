<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\NpsResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpsResponseFactory extends Factory
{
    protected $model = NpsResponse::class;

    public function definition(): array
    {
        $score = fake()->numberBetween(0, 10);

        return [
            'user_id' => User::factory(),
            'booking_id' => Booking::factory(),
            'survey_code' => 'NPS-'.fake()->numerify('####'),
            'score' => $score,
            'category' => $score >= 9 ? 'promoter' : ($score >= 7 ? 'passive' : 'detractor'),
            'comment' => fake()->optional()->sentence(),
            'locale' => 'fr',
            'responded_at' => now(),
            'metadata' => [],
        ];
    }
}
