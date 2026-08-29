<?php

namespace Database\Factories;

use App\Models\PeerRental;
use App\Models\PeerReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerReview> */
class PeerReviewFactory extends Factory
{
    protected $model = PeerReview::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_rental_id' => PeerRental::factory(),
            'author_id' => User::factory(),
            'target_id' => User::factory(),
            'author_role' => PeerReview::ROLE_LOCATAIRE,
            'rating' => 5,
            'comment' => $this->faker->sentence(8),
            'submitted_at' => now(),
        ];
    }
}
