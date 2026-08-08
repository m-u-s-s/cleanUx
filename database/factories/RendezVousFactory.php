<?php

namespace Database\Factories;

use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RendezVous>
 *
 * `rendez_vous` n'impose aucune colonne : tout y est nullable ou pourvu d'un défaut. On renseigne
 * néanmoins de quoi obtenir un rendez-vous PLAUSIBLE — un modèle vide passerait les contraintes
 * sans rien apprendre à qui l'utilise dans un test.
 */
class RendezVousFactory extends Factory
{
    protected $model = RendezVous::class;

    public function definition(): array
    {
        $quand = fake()->dateTimeBetween('+1 day', '+3 weeks');

        return [
            'client_id' => User::factory(),
            'employe_id' => User::factory(),
            'status' => 'en_attente',
            'date' => $quand->format('Y-m-d'),
            'heure' => $quand->format('H:i:s'),
            'scheduled_at' => $quand,
            'adresse' => fake()->streetAddress(),
            'ville' => fake()->city(),
            'code_postal' => fake()->postcode(),
            'description' => fake()->sentence(),
            'estimated_price' => fake()->randomFloat(2, 40, 400),
        ];
    }

    public function confirme(): static
    {
        return $this->state(fn () => ['status' => 'confirme']);
    }
}
