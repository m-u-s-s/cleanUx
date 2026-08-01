<?php

namespace Database\Factories;

use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripTrackingPointFactory extends Factory
{
    protected $model = TripTrackingPoint::class;

    /**
     * Compteur monotone pour `client_sequence`.
     *
     * La table porte un index UNIQUE sur `(session_id, client_sequence)`, et cette colonne était
     * tirée au hasard entre 1 et 100. Créer trois points sur une même session — ce que fait le test
     * du détail de session — collisionne donc environ trois fois sur cent : la suite échouait une
     * exécution sur trente-quatre, sur un test qui n'avait rien fait de mal.
     *
     * Un compteur au lieu d'un tirage : la valeur n'a aucune signification métier dans une
     * fixture, seule son unicité compte.
     */
    private static int $nextSequence = 1;

    public function definition(): array
    {
        return [
            'session_id' => fn () => TripTrackingSession::factory()->create()->id,
            'lat' => fake()->latitude(49.5, 51.5),
            'lng' => fake()->longitude(2.5, 6.5),
            'accuracy_m' => fake()->randomFloat(1, 3.0, 50.0),
            'speed_mps' => fake()->randomFloat(2, 0.0, 20.0),
            'heading_deg' => fake()->numberBetween(0, 359),
            'cumulative_distance_m' => fake()->numberBetween(0, 5000),
            'distance_to_dest_m' => fake()->numberBetween(100, 10000),
            'eta_seconds' => fake()->numberBetween(60, 3600),
            'client_sequence' => self::$nextSequence++,
            'recorded_at' => now(),
            'created_at' => now(),
        ];
    }
}
