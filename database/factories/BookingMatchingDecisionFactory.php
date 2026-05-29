<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingMatchingDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingMatchingDecisionFactory extends Factory
{
    protected $model = BookingMatchingDecision::class;

    public function definition(): array
    {
        $topScore = fake()->randomFloat(2, 60, 100);
        $selectedScore = fake()->randomFloat(2, 50, (float) $topScore);
        $runnerUpScore = fake()->optional()->randomFloat(2, 40, (float) $selectedScore);

        return [
            'booking_id' => Booking::factory(),
            'selected_user_id' => User::factory(),
            'candidates_count' => fake()->numberBetween(1, 20),
            'selected_score' => number_format($selectedScore, 2),
            'top_score' => number_format($topScore, 2),
            'runner_up_score' => $runnerUpScore ? number_format($runnerUpScore, 2) : null,
            'algorithm_version' => 'v2',
            'strategy' => 'weighted_score',
            'weights_snapshot' => [
                'rating' => 0.30,
                'distance' => 0.25,
                'acceptance' => 0.20,
                'availability' => 0.15,
                'completion' => 0.10,
            ],
            'candidates_breakdown' => [
                ['user_id' => fake()->numberBetween(1, 999), 'score' => $topScore],
            ],
            'selected_breakdown' => [
                'rating' => fake()->randomFloat(2, 0, 30),
                'distance' => fake()->randomFloat(2, 0, 25),
                'acceptance' => fake()->randomFloat(2, 0, 20),
            ],
            'metadata' => [],
        ];
    }
}
