<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\RatingReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingReportFactory extends Factory
{
    protected $model = RatingReport::class;

    public function definition(): array
    {
        return [
            'feedback_id' => fn () => Feedback::factory()->create()->id,
            'reporter_user_id' => fn () => User::factory()->create()->id,
            'reason' => fake()->randomElement([
                RatingReport::REASON_SPAM,
                RatingReport::REASON_OFFENSIVE,
                RatingReport::REASON_FAKE,
                RatingReport::REASON_OTHER,
            ]),
            'details' => fake()->optional()->sentence(),
            'status' => RatingReport::STATUS_PENDING,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'admin_note' => null,
        ];
    }

    public function keptAfterReview(): static
    {
        return $this->state(fn () => [
            'status' => RatingReport::STATUS_REVIEWED_KEPT,
            'reviewed_at' => now(),
        ]);
    }
}
