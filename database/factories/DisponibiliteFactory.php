<?php

namespace Database\Factories;

use App\Models\Disponibilite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisponibiliteFactory extends Factory
{
    protected $model = Disponibilite::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('now', '+14 days')->format('Y-m-d');
        $heureDebut = fake()->randomElement($this->horairesDisponibles());
        $heureFin = date('H:i:s', strtotime($heureDebut . ' +30 minutes'));

        return [
            'user_id' => User::factory()->employe(),
            'date' => $date,
            'heure_debut' => $heureDebut,
            'heure_fin' => $heureFin,
        ];
    }

    public function forEmploye(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }

    public function today(): static
    {
        return $this->state(function () {
            $heureDebut = fake()->randomElement($this->horairesDisponibles());

            return [
                'date' => now()->toDateString(),
                'heure_debut' => $heureDebut,
                'heure_fin' => date('H:i:s', strtotime($heureDebut . ' +30 minutes')),
            ];
        });
    }

    public function thisWeek(): static
    {
        return $this->state(function () {
            $date = now()->startOfWeek()->addDays(fake()->numberBetween(0, 6))->toDateString();
            $heureDebut = fake()->randomElement($this->horairesDisponibles());

            return [
                'date' => $date,
                'heure_debut' => $heureDebut,
                'heure_fin' => date('H:i:s', strtotime($heureDebut . ' +30 minutes')),
            ];
        });
    }

    private function horairesDisponibles(): array
    {
        return [
            '09:00:00',
            '09:30:00',
            '10:00:00',
            '10:30:00',
            '11:00:00',
            '11:30:00',
            '14:00:00',
            '14:30:00',
            '15:00:00',
            '15:30:00',
            '16:00:00',
            '16:30:00',
        ];
    }
}