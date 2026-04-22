<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\RendezVous;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'rendez_vous_id' => RendezVous::factory()->state([
                'status' => 'termine'
            ]),
            'client_id' => null,
            'note' => fake()->numberBetween(2, 5),
            'commentaire' => fake()->paragraph(1),
            'reponse_admin' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Feedback $feedback) {
                if ($feedback->rendez_vous_id && $feedback->rendezVous) {
                    $feedback->client_id = $feedback->rendezVous->client_id;
                }
            })
            ->afterCreating(function (Feedback $feedback) {
                $rdv = $feedback->rendezVous;

                if ($rdv && $feedback->client_id !== $rdv->client_id) {
                    $feedback->update([
                        'client_id' => $rdv->client_id,
                    ]);
                }
            });
    }

    public function forRendezVous(RendezVous $rdv): static
    {
        return $this->state(fn() => [
            'rendez_vous_id' => $rdv->id,
            'client_id' => $rdv->client_id,
        ]);
    }

    public function answered(): static
    {
        return $this->state(fn() => [
            'reponse_admin' => fake()->sentence(),
        ]);
    }

    public function unanswered(): static
    {
        return $this->state(fn() => [
            'reponse_admin' => null,
        ]);
    }

    public function highRating(): static
    {
        return $this->state(fn() => [
            'note' => fake()->numberBetween(4, 5),
        ]);
    }

    public function lowRating(): static
    {
        return $this->state(fn() => [
            'note' => fake()->numberBetween(1, 2),
        ]);
    }
}
